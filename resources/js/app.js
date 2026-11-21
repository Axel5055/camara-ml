/**
 * Arrastrar para desplazar tablas: en cualquier contenedor con scroll horizontal
 * (`.overflow-x-auto`) se puede mantener presionado el clic y mover el mouse
 * para recorrerlo sin usar la barra de abajo. Se delega en `document`, así
 * sigue funcionando cuando Livewire vuelve a pintar las tablas.
 */
const dragThreshold = 5;
const scrollSelector = 'ui-table-scroll-area, .overflow-x-auto';
const interactiveSelector = 'a, button, input, select, textarea, label, summary, [role="button"], [contenteditable="true"], [data-no-drag-scroll]';

let drag = null;

const scrollableContainer = (target) => {
    const container = target instanceof Element ? target.closest(scrollSelector) : null;

    return container && container.scrollWidth > container.clientWidth ? container : null;
};

document.addEventListener('mouseover', (event) => {
    const container = scrollableContainer(event.target);

    if (container) {
        container.classList.add('drag-scroll');
    }
});

document.addEventListener('pointerdown', (event) => {
    if (event.pointerType !== 'mouse' || event.button !== 0) {
        return;
    }

    const container = scrollableContainer(event.target);

    if (!container || event.target.closest(interactiveSelector)) {
        return;
    }

    drag = {
        container,
        startX: event.clientX,
        startScroll: container.scrollLeft,
        active: false,
    };
});

document.addEventListener('pointermove', (event) => {
    if (!drag) {
        return;
    }

    const distance = event.clientX - drag.startX;

    if (!drag.active) {
        if (Math.abs(distance) < dragThreshold) {
            return;
        }

        drag.active = true;
        drag.container.classList.add('drag-scrolling');
    }

    drag.container.scrollLeft = drag.startScroll - distance;
    event.preventDefault();
});

const endDrag = () => {
    if (!drag) {
        return;
    }

    const { container, active } = drag;

    drag = null;
    container.classList.remove('drag-scrolling');

    if (active) {
        const cancelClick = (event) => event.stopPropagation();

        document.addEventListener('click', cancelClick, { capture: true, once: true });
        setTimeout(() => document.removeEventListener('click', cancelClick, { capture: true }), 0);
    }
};

document.addEventListener('pointerup', endDrag);
document.addEventListener('pointercancel', endDrag);
