(() => {
    const MAX_FALLBACK_FILE_BYTES = 2 * 1024 * 1024;
    const fileInput = document.getElementById('file-input');
    const dropzone = document.getElementById('dropzone');
    const fileChip = document.getElementById('file-chip');
    const uploadForm = document.getElementById('upload-form');
    const uploadFeedback = document.getElementById('upload-feedback');
    const previewEmpty = document.getElementById('preview-empty');
    const previewFile = document.querySelector('.preview-shell');
    const previewImage = document.getElementById('preview-image');
    const previewIcon = document.getElementById('preview-icon');
    const previewName = document.getElementById('preview-name');
    const previewDetails = document.getElementById('preview-details');
    const clearButton = document.getElementById('clear-file');
    const maxFileBytes = Number.parseInt(fileInput?.dataset.maxFileBytes || '', 10) || MAX_FALLBACK_FILE_BYTES;
    const maxFileLabel = fileInput?.dataset.maxFileLabel || '2 MB';
    const allowedFileTypes = fileInput?.dataset.allowedFileTypes || '';
    const allowedExtensions = (fileInput?.accept || '')
        .split(',')
        .map((item) => item.trim().replace(/^\./, '').toLowerCase())
        .filter(Boolean);
    const hasUploadUi = Boolean(fileInput && dropzone && fileChip && uploadForm && previewEmpty && previewFile && previewImage && previewIcon && previewName && previewDetails && clearButton);
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
    const isFileTypeAllowed = (file) => {
        if (!file || allowedExtensions.length === 0) {
            return true;
        }

        const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';

        return allowedExtensions.includes(extension);
    };

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
        previewImage.hidden = true;
        previewIcon.hidden = true;
        previewFile.hidden = true;
        previewImage.src = '';
        previewEmpty.hidden = false;
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
        previewEmpty.hidden = true;
        previewFile.hidden = false;

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        if (file.type && file.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(file);
            previewImage.src = objectUrl;
            previewImage.hidden = false;
            previewIcon.hidden = true;
        } else {
            previewImage.hidden = true;
            previewIcon.hidden = false;
        }

        if (!isFileWithinLimit(file)) {
            setUploadFeedback(`File ini terlalu besar. Pilih file berukuran ${maxFileLabel} atau lebih kecil.`, 'error');
            previewDetails.textContent = `${formatBytes(file.size)} • Melebihi batas ${maxFileLabel}`;
            return;
        }

        if (!isFileTypeAllowed(file)) {
            setUploadFeedback(`Jenis file tidak didukung. Format yang diizinkan: ${allowedFileTypes}.`, 'error');
            previewDetails.textContent = `${formatBytes(file.size)} • Jenis file tidak didukung`;
            return;
        }

        clearUploadFeedback();
    };

    const getSelectedFile = () => (fileInput.files && fileInput.files[0] ? fileInput.files[0] : null);

    if (hasUploadUi) {
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

        uploadForm.addEventListener('submit', (event) => {
            const selectedFile = getSelectedFile();

            if (!selectedFile) {
                return;
            }

            if (!isFileWithinLimit(selectedFile)) {
                event.preventDefault();
                setUploadFeedback(`File ini terlalu besar. Pilih file berukuran ${maxFileLabel} atau lebih kecil.`, 'error');
                fileInput.focus();
                return;
            }

            if (!isFileTypeAllowed(selectedFile)) {
                event.preventDefault();
                setUploadFeedback(`Jenis file tidak didukung. Format yang diizinkan: ${allowedFileTypes}.`, 'error');
                fileInput.focus();
            }
        });

        resetPreview();
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

    const filePreviewImages = document.querySelectorAll('.file-preview');
    if (filePreviewImages.length > 0) {
        const lightbox = document.createElement('div');
        lightbox.className = 'file-lightbox';
        lightbox.hidden = true;
        lightbox.innerHTML = `
            <div class="file-lightbox-panel" role="dialog" aria-modal="true" aria-label="Preview gambar">
                <button class="file-lightbox-close" type="button" aria-label="Tutup preview">×</button>
                <img class="file-lightbox-image" alt="">
                <div class="file-lightbox-caption"></div>
            </div>
        `;

        document.body.append(lightbox);

        const lightboxImage = lightbox.querySelector('.file-lightbox-image');
        const lightboxCaption = lightbox.querySelector('.file-lightbox-caption');
        const closeButton = lightbox.querySelector('.file-lightbox-close');
        let activePreview = null;

        const closeLightbox = () => {
            lightbox.hidden = true;
            document.body.classList.remove('is-lightbox-open');
            lightboxImage.src = '';
            lightboxImage.alt = '';
            lightboxCaption.textContent = '';

            if (activePreview) {
                activePreview.focus();
                activePreview = null;
            }
        };

        const openLightbox = (preview) => {
            const previewSrc = preview.getAttribute('src');
            if (!previewSrc) {
                return;
            }

            activePreview = preview;
            lightboxImage.src = previewSrc;
            lightboxImage.alt = preview.getAttribute('alt') || 'Preview gambar';
            lightboxCaption.textContent = preview.getAttribute('alt') || '';
            lightbox.hidden = false;
            document.body.classList.add('is-lightbox-open');
            closeButton.focus();
        };

        filePreviewImages.forEach((preview) => {
            preview.tabIndex = 0;
            preview.setAttribute('role', 'button');

            preview.addEventListener('click', () => {
                openLightbox(preview);
            });

            preview.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                openLightbox(preview);
            });
        });

        closeButton.addEventListener('click', closeLightbox);

        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox) {
                closeLightbox();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !lightbox.hidden) {
                closeLightbox();
            }
        });
    }

    const accessForms = document.querySelectorAll('form[data-ajax="true"]');
    accessForms.forEach((form) => {
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        const fallbackSubmit = form.querySelector('.fallback-submit');
        
        if (fallbackSubmit) {
            fallbackSubmit.style.display = 'none';
        }

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', async () => {
                const formData = new FormData(form);
                formData.append('ajax', 'true');
                
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    
                    const data = await response.json();
                    if (data.status !== 'success') {
                        console.error('Access update failed:', data.status);
                        // Revert checkbox if failed
                        checkbox.checked = !checkbox.checked;
                    }
                } catch (error) {
                    console.error('Error updating access:', error);
                    // Revert checkbox if failed
                    checkbox.checked = !checkbox.checked;
                }
            });
        });
    });

})();
