@extends('layouts.app')
@section('title', 'Open Savings Account')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item active">Open Account</li>
@endsection
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Open Savings Account</div>
            <div class="card-body">
                <form method="POST" action="{{ route('savings.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                        <select name="client_id" class="form-select ts-select" required>
                            <option value="">Select client...</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" @selected(old('client_id', $selectedClient?->id)==$c->id)>{{ $c->name }} ({{ $c->client_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Savings Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id')==$p->id)>{{ $p->name }} (Min: {{ number_format($p->minimum_balance, $dp) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Opening Date</label>
                        <input type="date" name="opened_date" class="form-control" value="{{ old('opened_date', today()->toDateString()) }}">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Open Account</button>
                        <a href="{{ route('savings.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
