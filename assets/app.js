(() => {
    const MAX_FALLBACK_FILE_BYTES = 2 * 1024 * 1024;
    const fileInput = document.getElementById('file-input');
    const dropzone = document.getElementById('dropzone');
    const fileChip = document.getElementById('file-chip');
    const uploadForm = document.getElementById('upload-form');
    const uploadFeedback = document.getElementById('upload-feedback');
    const previewEmpty = document.getElementById('preview-empty');
    const previewImage = document.getElementById('preview-image');
    const previewIcon = document.getElementById('preview-icon');
    const previewName = document.getElementById('preview-name');
    const previewDetails = document.getElementById('preview-details');
    const clearButton = document.getElementById('clear-file');
    const maxFileBytes = Number.parseInt(fileInput?.dataset.maxFileBytes || '', 10) || MAX_FALLBACK_FILE_BYTES;
    const maxFileLabel = fileInput?.dataset.maxFileLabel || '2 MB';
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

    const setUploadFeedback = (message, tone = 'error') => {
        if (!uploadFeedback) {
            return;
        }

        uploadFeedback.textContent = message;
        uploadFeedback.hidden = false;
        uploadFeedback.classList.toggle('is-success', tone === 'success');
        uploadFeedback.classList.toggle('is-visible', true);
    };

    const clearUploadFeedback = () => {
        if (!uploadFeedback) {
            return;
        }

        uploadFeedback.textContent = '';
        uploadFeedback.hidden = true;
        uploadFeedback.classList.remove('is-visible', 'is-success');
    };

    const isFileWithinLimit = (file) => Boolean(file) && file.size <= maxFileBytes;

    const setFileChipLabel = (label) => {
        fileChip.replaceChildren();
        const strong = document.createElement('strong');
        strong.textContent = label;
        fileChip.append(strong);
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
        clearUploadFeedback();
    };

    const showPreview = (file) => {
        if (!file) {
            resetPreview();
            return;
        }

        setFileChipLabel(file.name);
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

        if (!isFileWithinLimit(file)) {
            setUploadFeedback(`File ini terlalu besar. Pilih file berukuran ${maxFileLabel} atau lebih kecil.`, 'error');
            previewDetails.textContent = `${formatBytes(file.size)} • Melebihi batas ${maxFileLabel}`;
            return;
        }

        clearUploadFeedback();
    };

    const getSelectedFile = () => (fileInput.files && fileInput.files[0] ? fileInput.files[0] : null);

    fileInput.addEventListener('change', () => {
        showPreview(getSelectedFile());
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

    if (uploadForm) {
        uploadForm.addEventListener('submit', (event) => {
            const selectedFile = getSelectedFile();

            if (!selectedFile) {
                return;
            }

            if (!isFileWithinLimit(selectedFile)) {
                event.preventDefault();
                setUploadFeedback(`File ini terlalu besar. Pilih file berukuran ${maxFileLabel} atau lebih kecil.`, 'error');
                fileInput.focus();
            }
        });
    }

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
