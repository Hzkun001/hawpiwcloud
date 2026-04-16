(() => {
    const fileInput = document.getElementById('file-input');
    const dropzone = document.getElementById('dropzone');
    const fileChip = document.getElementById('file-chip');
    const previewEmpty = document.getElementById('preview-empty');
    const previewImage = document.getElementById('preview-image');
    const previewIcon = document.getElementById('preview-icon');
    const previewName = document.getElementById('preview-name');
    const previewDetails = document.getElementById('preview-details');
    const clearButton = document.getElementById('clear-file');
    let objectUrl = null;

    const formatBytes = (bytes) => {
        if (bytes >= 1024 * 1024) {
            return `${(bytes / (1024 * 1024)).toFixed(2).replace(/\.00$/, '')} MB`;
        }
        if (bytes >= 1024) {
            return `${(bytes / 1024).toFixed(2).replace(/\.00$/, '')} KB`;
        }
        return `${bytes} B`;
    };

    const resetPreview = () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        fileChip.textContent = 'Belum ada berkas yang dipilih';
        previewName.textContent = 'Belum ada berkas yang dipilih';
        previewDetails.textContent = 'Ukuran dan jenis berkas akan tampil di sini.';
        previewImage.style.display = 'none';
        previewIcon.style.display = 'none';
        previewImage.src = '';
        previewEmpty.style.display = 'grid';
    };

    const showPreview = (file) => {
        if (!file) {
            resetPreview();
            return;
        }

        fileChip.innerHTML = `<strong>${file.name}</strong>`;
        previewName.textContent = file.name;
        previewDetails.textContent = `${formatBytes(file.size)} • ${file.type || 'Jenis berkas tidak dikenal'}`;
        previewEmpty.style.display = 'none';

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        if (file.type && file.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(file);
            previewImage.src = objectUrl;
            previewImage.style.display = 'block';
            previewIcon.style.display = 'none';
        } else {
            previewImage.style.display = 'none';
            previewIcon.style.display = 'flex';
        }
    };

    fileInput.addEventListener('change', () => {
        showPreview(fileInput.files && fileInput.files[0] ? fileInput.files[0] : null);
    });

    clearButton.addEventListener('click', () => {
        fileInput.value = '';
        resetPreview();
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const droppedFile = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
        if (!droppedFile) {
            return;
        }

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(droppedFile);
        fileInput.files = dataTransfer.files;
        showPreview(droppedFile);
    });

    const faqItems = document.querySelectorAll('[data-faq] .faq-item');

    faqItems.forEach((item) => {
        const questionButton = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');

        if (!questionButton || !answer) {
            return;
        }

        questionButton.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');

            faqItems.forEach((otherItem) => {
                const otherButton = otherItem.querySelector('.faq-question');

                otherItem.classList.remove('is-open');
                if (otherButton) {
                    otherButton.setAttribute('aria-expanded', 'false');
                }
            });

            if (!isOpen) {
                item.classList.add('is-open');
                questionButton.setAttribute('aria-expanded', 'true');
            } else {
                questionButton.setAttribute('aria-expanded', 'false');
            }
        });
    });

    resetPreview();
})();
