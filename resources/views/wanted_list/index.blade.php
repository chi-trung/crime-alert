@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-3 text-danger">Danh sách đối tượng truy nã</h1>
    {{-- Issue #343: this header listed four fields the table never shows —
         "có ảnh, mô tả, mức độ nguy hiểm, khen thưởng". The schema has no
         image, danger-level or reward columns at all (verified live on both
         dialects), and "mô tả" never matched a column either: `crime` is the
         charge, `decision`/`agency` are the warrant. Copied from the source
         portal's page, not from this table. Re-broadcasting it makes every
         row look half-broken. Describes the 7 columns actually rendered. --}}
    <p class="lead text-muted">Tổng hợp các đối tượng truy nã: họ tên, năm sinh, nơi đăng ký thường trú, tội danh và quyết định truy nã.</p>
    <form method="GET" action="{{ route('wanted_list.index') }}" class="mb-3 d-flex" role="search">
        <input type="search" id="wanted-q" name="q" class="form-control me-2" placeholder="Tìm theo tên, năm sinh, địa chỉ, tội danh..." value="{{ request('q') }}" aria-label="Tìm đối tượng truy nã">
        <button class="btn btn-danger" type="submit">Tìm kiếm</button>
    </form>
    {{-- Issue #343: the table markup was fully duplicated between the
         filled-q and empty-q branches — one branch renders it, the other
         branch renders it identically. Only the empty-result notice
         belongs inside the search branch; the table itself is q-agnostic. --}}
    @if(request()->filled('q') && $wantedPeople->count() === 0)
        <div class="alert alert-danger mt-4">Không tìm thấy đối tượng phù hợp.</div>
    @elseif($wantedPeople->count() > 0)
        <div class="table-responsive mt-4">
            <table class="table table-bordered table-hover align-middle bg-white">
                <thead class="table-danger">
                    <tr>
                        <th>STT</th>
                        <th>Họ tên</th>
                        <th>Năm sinh</th>
                        <th>Nơi ĐKTT</th>
                        <th>Họ tên bố/mẹ</th>
                        <th>Tội danh</th>
                        <th>Số QĐ</th>
                        <th>Đơn vị ra QĐ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($wantedPeople as $i => $person)
                    <tr>
                        <td>{{ $wantedPeople->firstItem() + $i }}</td>
                        <td>{{ $person->name }}</td>
                        <td>{{ $person->birth_year }}</td>
                        <td>{{ $person->address }}</td>
                        <td>{{ $person->parents }}</td>
                        <td>{{ $person->crime }}</td>
                        <td>{{ $person->decision }}</td>
                        <td>{{ $person->agency }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="d-flex justify-content-center mt-4">
                {{ $wantedPeople->links('pagination::bootstrap-4') }}
            </div>
        </div>
    @endif
    <div class="text-muted small mt-3">
        Nguồn dữ liệu: <a href="https://truyna.bocongan.gov.vn/" target="_blank" rel="noopener">Cổng thông tin truy nã Bộ Công An</a>
    </div>
</div>
@endsection 