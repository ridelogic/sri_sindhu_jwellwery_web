@extends('layouts.admin')

@section('title', 'Edit '.$customer->name)
@section('form_page', '1')

@section('content')
    @include('admin.customers._form', [
        'customer' => $customer,
        'action' => route('admin.customers.update', $customer),
        'method' => 'PUT',
        'title' => 'Edit Customer — '.$customer->name,
        'back' => route('admin.customers.show', $customer),
        'backLabel' => 'Back to Customer',
    ])
@endsection
