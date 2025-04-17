class FileUploader {
    constructor(options) {
        this.form = options.form;
        this.url = options.url;
        this.maxFileSize = options.maxFileSize;
        this.acceptedFiles = options.acceptedFiles;
        this.csrfToken = options.csrfToken;
        this.onComplete = options.onComplete;
        this.onError = options.onError;
        
        this.setupDropZone();
        this.setupFileInput();
    }

    setupDropZone() {
        const dropZone = this.form;
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            this.handleFiles(files);
        });

        // Create message elements
        const messageDiv = document.createElement('div');
        messageDiv.className = 'dz-message';
        messageDiv.innerHTML = `
            <h3>Drop files here or click to upload</h3>
            <p>Supported formats: JPG, PNG, WEBP, MP4, MOV</p>
        `;
        dropZone.appendChild(messageDiv);

        // Create progress container
        this.progressContainer = document.createElement('div');
        this.progressContainer.className = 'upload-progress';
        this.progressContainer.style.display = 'none';
        dropZone.appendChild(this.progressContainer);
    }

    setupFileInput() {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = this.acceptedFiles;
        input.style.display = 'none';
        this.form.appendChild(input);

        this.form.addEventListener('click', () => input.click());
        input.addEventListener('change', () => this.handleFiles(input.files));
    }

    handleFiles(files) {
        const validFiles = Array.from(files).filter(file => {
            const isValidType = this.acceptedFiles.split(',').some(type => 
                file.name.toLowerCase().endsWith(type.replace('.', ''))
            );
            const isValidSize = file.size <= this.maxFileSize * 1024 * 1024;
            
            if (!isValidType) {
                this.onError(file, `Invalid file type: ${file.name}. Supported formats: ${this.acceptedFiles}`);
                return false;
            }
            if (!isValidSize) {
                this.onError(file, `File too large: ${file.name}. Maximum size: ${this.maxFileSize}MB`);
                return false;
            }
            return true;
        });

        if (validFiles.length === 0) return;

        const formData = new FormData();
        validFiles.forEach(file => formData.append('file[]', file));

        this.uploadFiles(formData, validFiles);
    }

    showProgress(files) {
        this.progressContainer.style.display = 'block';
        this.progressContainer.innerHTML = files.map(file => `
            <div class="progress-item" data-file="${file.name}">
                <div class="progress-info">
                    <span class="filename">${file.name}</span>
                    <span class="progress-text">0%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress" style="width: 0%"></div>
                </div>
                <div class="status-message"></div>
            </div>
        `).join('');
    }

    updateProgress(fileName, percent, message = '') {
        const item = this.progressContainer.querySelector(`[data-file="${fileName}"]`);
        if (item) {
            item.querySelector('.progress').style.width = `${percent}%`;
            item.querySelector('.progress-text').textContent = `${percent}%`;
            if (message) {
                const statusEl = item.querySelector('.status-message');
                statusEl.textContent = message;
                statusEl.style.color = message.toLowerCase().includes('error') ? 'red' : 'green';
            }
        }
    }

    uploadFiles(formData, files) {
        this.showProgress(files);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', this.url, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', this.csrfToken);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                files.forEach(file => this.updateProgress(file.name, percent, 'Uploading...'));
            }
        };

        xhr.onload = () => {
            if (xhr.status !== 200) {
                files.forEach(file => this.updateProgress(file.name, 0, `Error: Server error (${xhr.status})`));
                this.onError(null, `Server error (${xhr.status})`);
                return;
            }

            let response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (error) {
                console.error('Failed to parse server response:', xhr.responseText);
                files.forEach(file => this.updateProgress(file.name, 100, 'Upload complete (processing)'));
                // Don't show error if the file was actually uploaded
                setTimeout(() => this.onComplete(), 1000);
                return;
            }

            if (response && response.files && response.files.length > 0) {
                files.forEach(file => {
                    const fileResponse = response.files.find(f => f.name.startsWith(file.name.split('.')[0]));
                    if (fileResponse) {
                        this.updateProgress(file.name, 100, 'Upload complete');
                    } else {
                        this.updateProgress(file.name, 100, 'Upload complete (processing)');
                    }
                });
                setTimeout(() => this.onComplete(), 1000);
            } else if (response && response.error) {
                files.forEach(file => this.updateProgress(file.name, 0, `Error: ${response.error}`));
                this.onError(null, response.error);
            } else {
                files.forEach(file => this.updateProgress(file.name, 100, 'Upload complete (processing)'));
                setTimeout(() => this.onComplete(), 1000);
            }
        };

        xhr.onerror = () => {
            files.forEach(file => this.updateProgress(file.name, 0, 'Error: Network error'));
            this.onError(null, 'Network error occurred');
            console.error('Network error during upload');
        };

        xhr.ontimeout = () => {
            files.forEach(file => this.updateProgress(file.name, 0, 'Error: Upload timeout'));
            this.onError(null, 'Upload timed out');
            console.error('Upload timed out');
        };

        // Set timeout for large files (10 minutes)
        xhr.timeout = 600000;

        try {
            xhr.send(formData);
        } catch (error) {
            files.forEach(file => this.updateProgress(file.name, 0, 'Error: Failed to send file'));
            this.onError(null, 'Failed to send file');
            console.error('Send error:', error);
        }
    }
} 