@extends('layouts.admin')

@section('title', 'Add Plan')
@section('form_page', '1')

@section('content')
    @include('admin.plans._form', [
        'plan' => $plan,
        'action' => route('admin.plans.store'),
        'method' => 'POST',
        'slugLocked' => false,
        'enrolmentsCount' => 0,
        'title' => 'Add Saving Scheme',
        'back' => route('admin.plans.index'),
        'backLabel' => 'Back to Schemes',
    ])
@endsection
