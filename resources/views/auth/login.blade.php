<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Site Manager')</title>

    {!! setResources(['bootstrap', 'jquery']) !!}
    {!! resource('sitemanager::css/login.css') !!}
</head>

<body>   
    <main>

        <div class="content">

            <form method="POST" action="{{ route('login') }}">
                @csrf
                
                <input type="text" 
                        class="@error('username')is-invalid @enderror" 
                        id="username" 
                        name="username" 
                        placeholder="Username"
                        value="{{ old('username') }}" 
                        required 
                        autofocus>
                @error('username')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

                <input type="password" 
                        class="@error('password')is-invalid @enderror" 
                        id="password" 
                        name="password" 
                        placeholder="Password"
                        required>
                @error('password')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

                <button type="submit">Login</button>
            </form>
        </div>

    </main>

    <footer>
        <img src="/images/sitemanager.svg" alt="Site Manager Logo" class="logo">
    </footer>
</body>
</html>