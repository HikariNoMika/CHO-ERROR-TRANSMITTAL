@extends('layouts.app')

@section('content')
{{-- No @section('page-title'): the branded heading below is the single title.
     Previously the page rendered "Sign In" in the page head *and* "MCA Patient
     Docs" in a separate card, which pushed the form 465px down the viewport. --}}
<div class="login-wrap">
    <div class="card">
        <div class="login-head">
            <h2>MCA Patient Docs</h2>
            <p>Sign in to your account</p>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                       placeholder="Email address" value="{{ old('email') }}">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       placeholder="Password">
            </div>

            <button type="submit" class="btn-primary" style="width:100%;">Sign in</button>
        </form>

        <p class="login-foot">
            <a href="{{ route('docs') }}">Documentation &amp; Guide</a>
        </p>
    </div>
</div>
@endsection