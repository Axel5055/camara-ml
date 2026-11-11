@include('partials.welcome-scene', [
    'title' => 'Bienvenido',
    'destination' => auth()->check() ? route('dashboard') : route('login'),
    'buttonLabel' => auth()->check() ? 'Ir al panel' : 'Iniciar sesión',
])
