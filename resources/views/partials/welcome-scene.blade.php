<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
@include('partials.head', ['title' => $title ?? 'Bienvenido'])
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;500;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<style>
  :root{
    --rojo:#e20e16;
    --gris:#2e3338;
    --fondo:#fbf8f4;
  }
  *{margin:0;padding:0;box-sizing:border-box}
  html,body{height:100%;overflow:hidden}
  body{
    font-family:'Montserrat',system-ui,sans-serif;
    background:radial-gradient(ellipse at 50% 40%, #ffffff 0%, var(--fondo) 55%, #f1e9e1 100%);
    color:var(--gris);
    display:flex;align-items:center;justify-content:center;
  }
  #particulas{position:fixed;inset:0;width:100%;height:100%;pointer-events:none}

  .halo{
    position:fixed;left:50%;top:44%;
    width:70vmin;height:70vmin;transform:translate(-50%,-50%) scale(.4);
    border-radius:50%;
    background:radial-gradient(circle, rgba(226,14,22,.16) 0%, rgba(226,14,22,.05) 40%, transparent 70%);
    opacity:0;filter:blur(10px);pointer-events:none;
  }

  .escena{position:relative;display:flex;flex-direction:column;align-items:center;gap:2.2vmin;margin-top:-3vmin;padding:0 16px;max-width:100%}

  .logo{position:relative;width:min(64vmin,520px);aspect-ratio:1/1}
  .capa{position:absolute;display:block}
  .capa img{width:100%;height:100%;display:block;user-select:none;-webkit-user-drag:none}

  #rooster{
    -webkit-mask-image:linear-gradient(115deg,#000 40%,transparent 60%);
            mask-image:linear-gradient(115deg,#000 40%,transparent 60%);
    -webkit-mask-size:300% 100%;mask-size:300% 100%;
    -webkit-mask-position:100% 0;mask-position:100% 0;
    transform-origin:60% 100%;
  }

  .canto{position:absolute;pointer-events:none;overflow:visible}
  .canto path{fill:none;stroke:var(--rojo);stroke-width:7;stroke-linecap:round;opacity:0}

  .recorte{position:absolute;overflow:hidden}
  .recorte .capa{position:absolute;inset:0}

  .brillo{
    position:absolute;inset:0;pointer-events:none;
    background:linear-gradient(105deg,transparent 35%,rgba(255,255,255,.85) 50%,transparent 65%);
    background-size:250% 100%;background-position:150% 0;
    -webkit-mask-size:100% 100%;mask-size:100% 100%;
  }

  .bienvenida{text-align:center;opacity:0}
  .bienvenida h1{
    font-weight:300;font-size:clamp(18px,3.4vmin,30px);letter-spacing:.5em;text-transform:uppercase;
    padding-left:.5em;
  }
  .bienvenida h1 b{font-weight:700;color:var(--rojo)}
  .bienvenida .usuario{margin-top:1.4vmin;font-weight:700;font-size:clamp(20px,4vmin,36px);letter-spacing:.08em;color:var(--rojo);text-transform:uppercase;overflow-wrap:anywhere}
  .bienvenida p{margin-top:1vmin;font-size:clamp(11px,1.6vmin,14px);letter-spacing:.25em;color:#8a8f94;text-transform:uppercase}

  .carga{width:min(42vmin,340px);height:3px;background:rgba(46,51,56,.1);border-radius:3px;overflow:hidden;opacity:0;position:relative}
  .carga span{position:absolute;inset:0;transform-origin:left;transform:scaleX(0);
    background:linear-gradient(90deg,var(--rojo),#ff5a4f);border-radius:3px}
  .porcentaje{font-size:12px;letter-spacing:.2em;color:#9aa0a5;opacity:0;margin-top:-1vmin;font-variant-numeric:tabular-nums}

  .acciones{display:flex;gap:12px;opacity:0;pointer-events:none}
  .btn{
    display:inline-block;text-decoration:none;
    font-family:inherit;font-size:13px;font-weight:500;letter-spacing:.25em;text-transform:uppercase;
    padding:14px 34px;border-radius:40px;cursor:pointer;border:1.5px solid var(--rojo);
    background:var(--rojo);color:#fff;transition:transform .25s, box-shadow .25s, background .25s;
    box-shadow:0 8px 24px rgba(226,14,22,.25);
  }
  .btn:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(226,14,22,.35)}

  .cortina{position:fixed;inset:0;background:var(--rojo);transform:scaleY(0);transform-origin:bottom;z-index:10;pointer-events:none}

  .saltar{position:fixed;right:22px;bottom:18px;font-size:11px;letter-spacing:.2em;color:#b0b5ba;text-transform:uppercase;cursor:pointer;user-select:none}
  .saltar:hover{color:var(--rojo)}

  @media (max-aspect-ratio:1/1){
    .logo{width:min(84vw,520px)}
  }
  @media (max-width:480px){
    .bienvenida h1{letter-spacing:.3em;padding-left:.3em}
    .bienvenida p{letter-spacing:.15em}
  }
</style>
@if ($autoEnter ?? false)
<noscript><meta http-equiv="refresh" content="1;url={{ $destination }}"></noscript>
@endif
</head>
<body>

@php
    $capas = [
        'rooster' => [175, 69, 798, 628],
        'ml' => [295, 561, 644, 710],
        'grupo' => [280, 733, 656, 782],
        'comercial' => [167, 805, 862, 852],
        'lineL' => [153, 894, 280, 900],
        'lineR' => [744, 894, 871, 900],
        'tag' => [280, 886, 744, 911],
    ];
    $estiloCapa = fn (array $b): string => sprintf(
        'left:%s%%;top:%s%%;width:%s%%;height:%s%%',
        $b[0] / 1024 * 100,
        $b[1] / 1024 * 100,
        ($b[2] - $b[0]) / 1024 * 100,
        ($b[3] - $b[1]) / 1024 * 100,
    );
    $imagen = fn (string $k): string => asset('images/bienvenida/'.$k.'.png');
@endphp

<canvas id="particulas"></canvas>
<div class="halo"></div>

<main class="escena">
  <div class="logo" id="logo">
    <div class="capa" id="rooster" style="{{ $estiloCapa($capas['rooster']) }}"><img src="{{ $imagen('rooster') }}" alt="ML Grupo Comercial"></div>

    <div class="recorte" style="{{ $estiloCapa($capas['ml']) }}">
      <div class="capa" id="ml"><img src="{{ $imagen('ml') }}" alt=""></div>
      <div class="brillo" id="brillo" style="-webkit-mask-image:url({{ $imagen('ml') }});mask-image:url({{ $imagen('ml') }})"></div>
    </div>

    @foreach (['grupo', 'comercial', 'lineL', 'lineR', 'tag'] as $clave)
      <div class="capa" id="{{ $clave }}" style="{{ $estiloCapa($capas[$clave]) }}"><img src="{{ $imagen($clave) }}" alt=""></div>
    @endforeach

    <svg class="canto" id="canto" viewBox="0 0 120 120" style="left:{{ 790 / 1024 * 100 }}%;top:{{ 70 / 1024 * 100 }}%;width:{{ 120 / 1024 * 100 }}%;height:{{ 120 / 1024 * 100 }}%">
      <path d="M20 40 Q40 60 20 80"/>
      <path d="M45 25 Q75 60 45 95"/>
      <path d="M70 10 Q110 60 70 110"/>
    </svg>
  </div>

  <div class="bienvenida" id="bienvenida">
    <h1>Bienvenido</h1>
    @if (filled($userName ?? null))
      <div class="usuario">{{ $userName }}</div>
    @endif
    <p id="subtitulo">{{ $subtitle ?? 'Sistema de cámaras de refrigeración' }}</p>
  </div>
  <div class="carga" id="carga"><span></span></div>
  <div class="porcentaje" id="porcentaje">0%</div>
  @if (! ($autoEnter ?? false))
    <div class="acciones" id="acciones">
      <a class="btn" id="entrar" href="{{ $destination }}">{{ $buttonLabel }}</a>
    </div>
  @endif
</main>

<div class="saltar" id="saltar">Saltar ›</div>
<div class="cortina" id="cortina"></div>

<script>
const URL_SISTEMA = @json($destination);
const AUTO_ENTRAR = @json((bool) ($autoEnter ?? false));
const TEXTO_CARGA = @json($loadingText ?? '');

const logo = document.getElementById('logo');
const rooster = document.getElementById('rooster');
const ml = document.getElementById('ml');
const brillo = document.getElementById('brillo');

/* ===================== PARTÍCULAS ===================== */
const cv = document.getElementById('particulas'), ctx = cv.getContext('2d');
let W, H, parts = [];
function resize(){ W = cv.width = innerWidth * devicePixelRatio; H = cv.height = innerHeight * devicePixelRatio; }
addEventListener('resize', resize); resize();
const nuevaParticula = (rafaga = false) => {
  const cx = W / 2, cy = H * 0.42;
  return rafaga ? {
    x: cx, y: cy, vx: (Math.random() - .5) * 14 * devicePixelRatio, vy: (Math.random() - .7) * 12 * devicePixelRatio,
    r: (Math.random() * 3 + 1.2) * devicePixelRatio, a: 1, decay: .012 + Math.random() * .012, g: .12 * devicePixelRatio,
    c: Math.random() < .75 ? '226,14,22' : '46,51,56'
  } : {
    x: Math.random() * W, y: H + 20, vx: (Math.random() - .5) * .3, vy: -(Math.random() * .6 + .25) * devicePixelRatio,
    r: (Math.random() * 2.2 + .6) * devicePixelRatio, a: Math.random() * .35 + .1, decay: 0, g: 0,
    c: '226,14,22', w: Math.random() * Math.PI * 2
  };
};
for (let i = 0; i < 45; i++) { const p = nuevaParticula(); p.y = Math.random() * H; parts.push(p); }
const estado = { ambiente: 0 };
(function loop(){
  ctx.clearRect(0, 0, W, H);
  for (let i = parts.length - 1; i >= 0; i--) {
    const p = parts[i];
    p.vy += p.g; p.x += p.vx + (p.w !== undefined ? Math.sin((p.w += .02)) * .3 : 0); p.y += p.vy;
    if (p.decay) { p.a -= p.decay; p.vx *= .98; }
    if (p.a <= 0 || p.y < -20) { parts.splice(i, 1); if (!p.decay) parts.push(nuevaParticula()); continue; }
    ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(${p.c},${p.a * (p.decay ? 1 : estado.ambiente)})`; ctx.fill();
  }
  requestAnimationFrame(loop);
})();
function rafaga(n = 70){ for (let i = 0; i < n; i++) parts.push(nuevaParticula(true)); }

/* ===================== LÍNEA DE TIEMPO ===================== */
let tl;
function construir(){
  gsap.set(rooster, { webkitMaskPosition: '100% 0', maskPosition: '100% 0', opacity: 1, scale: .92, rotate: 0, filter: 'blur(6px) drop-shadow(0 0 0 rgba(226,14,22,0))' });
  gsap.set(ml, { yPercent: 110 });
  gsap.set(brillo, { backgroundPosition: '150% 0' });
  gsap.set('#grupo', { clipPath: 'inset(0 50% 0 50%)', opacity: 1 });
  gsap.set('#comercial', { opacity: 0, scaleX: 1.25, filter: 'blur(8px)' });
  gsap.set('#lineL', { scaleX: 0, transformOrigin: 'right center' });
  gsap.set('#lineR', { scaleX: 0, transformOrigin: 'left center' });
  gsap.set('#tag', { opacity: 0, y: 10 });
  gsap.set('#canto path', { opacity: 0, scale: .6, transformOrigin: '0% 50%' });
  gsap.set('.halo', { opacity: 0, scale: .4 });
  gsap.set(['#bienvenida', '#carga', '#porcentaje', '#acciones'].filter(s => document.querySelector(s)), { opacity: 0, y: 12 });
  gsap.set('#carga span', { scaleX: 0 });
  if (document.getElementById('acciones')) { gsap.set('#acciones', { pointerEvents: 'none' }); }
  gsap.set(logo, { y: 0 });

  tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
  tl
    .to('.halo', { opacity: 1, scale: 1, duration: 1.6, ease: 'power2.out' }, 0)
    .to({}, { duration: .01, onStart: () => gsap.to(estado, { ambiente: 1, duration: 2 }) }, 0)
    .to(rooster, { webkitMaskPosition: '0% 0', maskPosition: '0% 0', duration: 1.6, ease: 'power2.inOut' }, .2)
    .to(rooster, { scale: 1, filter: 'blur(0px) drop-shadow(0 0 0 rgba(226,14,22,0))', duration: 1.4 }, .3)
    .to(rooster, { rotate: -3, duration: .18, ease: 'power2.out' }, 1.75)
    .to(rooster, { rotate: 0, duration: .9, ease: 'elastic.out(1,.35)' }, 1.93)
    .to('#canto path', { opacity: 1, scale: 1, duration: .35, stagger: .1, ease: 'back.out(2)' }, 1.8)
    .to('#canto path', { opacity: 0, x: 12, duration: .5, stagger: .1, ease: 'power1.in' }, 2.25)
    .add(() => rafaga(60), 1.8)
    .to(rooster, { filter: 'blur(0px) drop-shadow(0 0 18px rgba(226,14,22,.45))', duration: .4, yoyo: true, repeat: 1 }, 1.8)
    .to(ml, { yPercent: 0, duration: 1, ease: 'back.out(1.6)' }, 2.1)
    .to(brillo, { backgroundPosition: '-50% 0', duration: 1.1, ease: 'power2.inOut' }, 2.9)
    .to('#grupo', { clipPath: 'inset(0 0% 0 0%)', duration: .8, ease: 'power3.inOut' }, 2.6)
    .to('#comercial', { opacity: 1, scaleX: 1, filter: 'blur(0px)', duration: .9 }, 2.9)
    .to(['#lineL', '#lineR'], { scaleX: 1, duration: .7, ease: 'power2.out' }, 3.3)
    .to('#tag', { opacity: 1, y: 0, duration: .8 }, 3.45)
    .to('#bienvenida', { opacity: 1, y: 0, duration: .8 }, 3.8)
    .to(['#carga', '#porcentaje'], { opacity: 1, y: 0, duration: .6, stagger: .1 }, 4.0)
    .to('#carga span', { scaleX: 1, duration: 2.2, ease: 'power1.inOut',
        onUpdate: function(){ document.getElementById('porcentaje').textContent = (TEXTO_CARGA ? TEXTO_CARGA + ' ' : '') + Math.round(this.progress() * 100) + '%'; } }, 4.2)
    .to(['#carga', '#porcentaje'], { opacity: 0, y: -8, duration: .4 }, 6.5);

  if (AUTO_ENTRAR) {
    tl.add(() => salir(), 6.9);
  } else {
    tl.to('#acciones', { opacity: 1, y: 0, duration: .6, pointerEvents: 'auto' }, 6.7);
  }

  gsap.to(logo, { y: -8, duration: 2.6, ease: 'sine.inOut', yoyo: true, repeat: -1, delay: 4 });
}

let saliendo = false;
function salir(evento){
  if (evento) { evento.preventDefault(); }
  if (saliendo) { return; }
  saliendo = true;
  gsap.timeline()
    .to('.escena', { scale: .96, opacity: 0, duration: .5, ease: 'power2.in' })
    .to('#cortina', { scaleY: 1, duration: .6, ease: 'power3.inOut' }, '-=.2')
    .add(() => { location.href = URL_SISTEMA; });
}

const botonEntrar = document.getElementById('entrar');
if (botonEntrar) { botonEntrar.addEventListener('click', salir); }
document.getElementById('saltar').addEventListener('click', () => {
  if (AUTO_ENTRAR) { salir(); } else if (tl) { tl.progress(1); }
});

// Respaldo: si la animación no arranca (sin internet, GSAP no cargó), entra igual.
if (AUTO_ENTRAR) { setTimeout(() => { location.href = URL_SISTEMA; }, 14000); }

const imagenes = [...logo.querySelectorAll('img')].map(i => i.decode().catch(() => {}));
Promise.all([...imagenes, document.fonts.ready]).then(construir);
</script>
</body>
</html>
