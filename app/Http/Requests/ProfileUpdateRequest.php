<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // is_scalar() before any cast: rules() evaluates BEFORE validation, so
        // the #160 crafted input email[]=... is still live here, and an
        // (string) array cast raises a warning PHPUnit converts to an
        // exception — which would 500 the very rejection #160 pinned. A
        // non-scalar email cannot equal a stored email, so it counts as
        // "changing" and its current_password leg simply fails with the
        // email rule's own error first.
        //
        // mb_strtolower on both sides: the #160 'lowercase' rule REJECTS a
        // non-lowercase email rather than normalizing it, so a user who
        // retypes their own address as OWNER@example.com must hear exactly
        // one complaint (the lowercase rule), not that plus a baffling
        // password demand for a move that cannot happen. Case-insensitive
        // comparison also matches how the email is matched everywhere else
        // it matters (the unique rule's collision semantics on MySQL).
        $email = $this->input('email');
        $changingEmail = is_scalar($email)
            && mb_strtolower((string) $email) !== mb_strtolower((string) $this->user()->email);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                // Issue #160: see RegisteredUserController — 'string' does not
                // halt the chain, so email[]=... reached 'lowercase' ->
                // mb_strtolower(array) -> TypeError 500 on PATCH /profile too.
                'bail',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Issue #253: the profile form was the only door that could move
            // the account's RECOVERY address, and it was Breeze-stock
            // (name+email only, no credential). One authenticated request —
            // from a session a stored-XSS payload (#2/#18/#77 are this repo's
            // own history) or an unattended browser walked through — repointed
            // users.email to an attacker mailbox, and forgot/reset-password
            // (guest routes keyed purely on users.email, no verified
            // requirement) then handed over the account permanently:
            // PasswordResetLinkController->sendResetLink($newEmail) reaches
            // the attacker, NewPasswordController->reset forceFills a password
            // the real owner can never type again. Re-authentication is
            // therefore demanded for exactly the transition that matters: an
            // email that differs from the stored one. #203's saving hook swept
            // reset tokens of the OLD address and #129/#237 reset
            // email_verified_at, so the codebase already recognised a moved
            // email as security-relevant — it just never gated who may move it.
            // Shape mirrors #154's destroy()/changePassword() credential:
            // 'string' declares the type and 'bail' is what actually stops the
            // crash, because the Validator runs later rules on an attribute
            // even after one fails, so email[]=... must not reach
            // current_password (password_verify(array) -> TypeError 500).
            // The 'confirmed' variant is deliberately NOT used: the delete
            // partial proves the app accepts a single-entry confirm, and
            // double-typing a password on every rename-adjacent save is UX
            // drag the takeover risk does not justify. When the address is
            // NOT moving, the leg stays nullable without the credential rule:
            // a stray (wrong) value alongside a name-only edit must not
            // reject an edit the gate never needed to cover.
            'current_password' => $changingEmail
                ? ['required', 'string', 'bail', 'current_password']
                : ['nullable', 'string'],
        ];
    }

    /**
     * Messages for the conditional credential, in the app's language.
     *
     * lang/vi/validation.php already answers 'Mật khẩu không đúng.' for any
     * current_password failure, so the required key is the real addition —
     * without it, a hijacked-session attempt on a Vietnamese UI shows the
     * English stock text — and the credential key is contextualized to
     * state the outcome (the email did not move). NB the key is
     * attribute.RULENAME, and the rule's name IS current_password —
     * 'current_password.current' (shortened) silently never matches.
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại để đổi email.',
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng, nên email không được đổi.',
        ];
    }

    /**
     * The email as first presented to this request, before any fill().
     *
     * ProfileController::update() needs to know whether the write actually
     * moved the address in order to apply the #27/#123/#204 rotation, and by
     * the time it asks, the model is already dirty/clean. Capturing the
     * decision here (validated(), not the raw bag) means the controller and
     * the gate cannot disagree about what "changed" means — so this body
     * must stay byte-identical to the rules() computation, fold-case
     * included.
     */
    public function emailIsChanging(): bool
    {
        $email = $this->input('email');

        return is_scalar($email)
            && mb_strtolower((string) $email) !== mb_strtolower((string) $this->user()->email);
    }
}
