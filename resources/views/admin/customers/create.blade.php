@extends('layouts.admin')

@section('title', 'Add Customer')
@section('form_page', '1')

@section('content')
    @include('admin.customers._form', [
        'customer' => $customer,
        'action' => route('admin.customers.store'),
        'method' => 'POST',
        'title' => 'Add Customer',
        'back' => route('admin.customers.index'),
        'backLabel' => 'Back to Customers',
    ])
@endsection
