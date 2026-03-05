document.addEventListener('alpine:init', () => {
    Alpine.data('lightbox', (images = []) => ({
        open: false,
        current: 0,
        images: images,
        show(index) { this.current = index; this.open = true; },
        close() { this.open = false; },
        prev() { this.current = (this.current - 1 + this.images.length) % this.images.length; },
        next() { this.current = (this.current + 1) % this.images.length; },
    }));
});
