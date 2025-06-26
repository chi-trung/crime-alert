@extends('layouts.app')
@section('content')
<div class="container py-5">
    <h1 class="display-5 fw-bold mb-3 text-danger"><i class="fas fa-user-secret me-2"></i>Danh sách đối tượng truy nã</h1>
    <p class="lead text-muted">Tổng hợp các đối tượng truy nã, có ảnh, mô tả, mức độ nguy hiểm, khen thưởng nếu cung cấp thông tin.</p>
    <form method="GET" action="{{ route('wanted_list.index') }}" class="mb-3 d-flex" role="search">
        <input type="text" name="q" class="form-control me-2" placeholder="Tìm theo tên, năm sinh, địa chỉ, tội danh..." value="{{ request('q') }}">
        <button class="btn btn-danger" type="submit"><i class="fas fa-search"></i> Tìm kiếm</button>
    </form>
    @if(request()->filled('q'))
        @if($wantedPeople->count() === 0)
            <div class="alert alert-danger mt-4">Không tìm thấy đối tượng phù hợp.</div>
        @else
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
                {{ $wantedPeople->links() }}
            </div>
        </div>
        @endif
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
                {{ $wantedPeople->links() }}
            </div>
        </div>
    @endif
    <div class="text-muted small mt-3">
        Nguồn dữ liệu: <a href="https://truyna.bocongan.gov.vn/" target="_blank">Cổng thông tin truy nã Bộ Công An</a>
    </div>
</div>
@endsection 