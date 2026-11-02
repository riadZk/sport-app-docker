<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Reverb Test</title>
</head>
<body>

<div id="messages"></div>

@vite(['resources/js/app.js'])

<script>
document.addEventListener('DOMContentLoaded', () => {

            setTimeout(() => {

    window.Echo.channel('chat')
        .listen('.message.sent', (e) => {
            console.log('Message received:', e);
            document.getElementById('messages').innerHTML +=`<p>${e.message}</p>`;
        });
        }, 500);

});
</script>

</body>
</html>
