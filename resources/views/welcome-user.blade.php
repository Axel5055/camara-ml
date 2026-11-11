@include('partials.welcome-scene', [
    'title' => 'Bienvenido',
    'userName' => $userName,
    'subtitle' => 'Preparando tu sistema',
    'loadingText' => 'Cargando sistema',
    'destination' => $destination,
    'autoEnter' => true,
])
