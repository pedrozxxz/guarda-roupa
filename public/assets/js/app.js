document.documentElement.classList.add('js-enabled');

document.addEventListener('DOMContentLoaded', () => {
    const menuButton = document.querySelector('[data-menu-toggle]');
    const navigation = document.querySelector('#main-nav');

    if (menuButton && navigation) {
        menuButton.addEventListener('click', () => {
            const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
            menuButton.setAttribute('aria-expanded', String(!isExpanded));
            navigation.classList.toggle('is-open', !isExpanded);
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const fieldId = button.getAttribute('data-password-toggle');
            const field = fieldId ? document.getElementById(fieldId) : null;

            if (!(field instanceof HTMLInputElement)) {
                return;
            }

            const shouldShow = field.type === 'password';
            field.type = shouldShow ? 'text' : 'password';
            button.textContent = shouldShow ? 'Ocultar' : 'Mostrar';
            button.setAttribute(
                'aria-label',
                shouldShow ? 'Ocultar senha' : 'Mostrar senha'
            );
        });
    });

    const pieceImageUpload = document.querySelector('[data-piece-upload]');
    const pieceImagePreview = document.querySelector('[data-piece-image-preview]');
    const pieceImagePlaceholder = document.querySelector('[data-piece-image-placeholder]');

    if (pieceImageUpload instanceof HTMLInputElement && pieceImagePreview instanceof HTMLImageElement) {
        let previewUrl = null;
        const updatePieceImagePreview = () => {
            const selectedFile = pieceImageUpload.files?.[0];
            const previewContainer = pieceImagePreview.closest('.piece-image-preview');

            if (previewUrl !== null) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }

            if (selectedFile) {
                previewUrl = URL.createObjectURL(selectedFile);
                previewContainer?.style.setProperty('aspect-ratio', '4 / 5');
                pieceImagePreview.src = previewUrl;
                pieceImagePreview.hidden = false;
                if (pieceImagePlaceholder) {
                    pieceImagePlaceholder.hidden = true;
                }
                previewContainer?.classList.remove('is-empty');
            } else {
                pieceImagePreview.onload = null;
                pieceImagePreview.removeAttribute('src');
                pieceImagePreview.hidden = true;
                previewContainer?.style.removeProperty('aspect-ratio');
                if (pieceImagePlaceholder) {
                    pieceImagePlaceholder.hidden = false;
                }
                previewContainer?.classList.add('is-empty');
            }
        };

        pieceImageUpload.addEventListener('change', updatePieceImagePreview);
    }
});
