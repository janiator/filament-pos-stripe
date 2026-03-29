<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PowerOffice</title>
</head>
<body style="font-family: system-ui, sans-serif; max-width: 40rem; margin: 3rem auto; padding: 0 1rem;">
    <h1 style="font-size: 1.25rem;">{{ $success ? __('Success') : __('Something went wrong') }}</h1>
    <p>{{ $message }}</p>
</body>
</html>
