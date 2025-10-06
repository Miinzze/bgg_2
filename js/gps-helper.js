// GPS Auto-Erfassung
class GPSHelper {
    constructor() {
        this.currentPosition = null;
        this.watchId = null;
    }
    
    // Aktuelle Position einmalig abrufen
    getCurrentPosition(successCallback, errorCallback) {
        if (!navigator.geolocation) {
            if (errorCallback) errorCallback('Geolocation wird nicht unterstützt');
            return;
        }
        
        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.currentPosition = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };
                if (successCallback) successCallback(this.currentPosition);
            },
            (error) => {
                let message = 'GPS-Fehler: ';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message += 'Zugriff verweigert. Bitte erlauben Sie den Standortzugriff.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message += 'Position nicht verfügbar.';
                        break;
                    case error.TIMEOUT:
                        message += 'Zeitüberschreitung.';
                        break;
                    default:
                        message += 'Unbekannter Fehler.';
                }
                if (errorCallback) errorCallback(message);
            },
            options
        );
    }
    
    // Position kontinuierlich verfolgen
    watchPosition(updateCallback, errorCallback) {
        if (!navigator.geolocation) {
            if (errorCallback) errorCallback('Geolocation wird nicht unterstützt');
            return;
        }
        
        const options = {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0
        };
        
        this.watchId = navigator.geolocation.watchPosition(
            (position) => {
                this.currentPosition = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };
                if (updateCallback) updateCallback(this.currentPosition);
            },
            errorCallback,
            options
        );
    }
    
    // Position-Tracking stoppen
    stopWatching() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
    }
    
    // Position in Formular eintragen
    fillFormFields(latFieldId, lngFieldId) {
        if (this.currentPosition) {
            document.getElementById(latFieldId).value = this.currentPosition.lat;
            document.getElementById(lngFieldId).value = this.currentPosition.lng;
            return true;
        }
        return false;
    }
    
    // GPS-Status anzeigen
    showStatus(elementId, message, type = 'info') {
        const element = document.getElementById(elementId);
        if (element) {
            const colors = {
                'success': '#28a745',
                'error': '#dc3545',
                'info': '#007bff',
                'warning': '#ffc107'
            };
            
            element.innerHTML = `
                <div style="padding: 10px; background: ${colors[type]}20; border-left: 4px solid ${colors[type]}; border-radius: 5px; margin: 10px 0;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    ${message}
                </div>
            `;
        }
    }
}

// Kamera-Integration
class CameraHelper {
    constructor() {
        this.stream = null;
        this.videoElement = null;
    }
    
    // Kamera starten
    async startCamera(videoElementId, errorCallback) {
        try {
            // Prüfen ob MediaDevices API verfügbar ist
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Kamera-API wird nicht unterstützt');
            }
            
            // Video-Element holen
            this.videoElement = document.getElementById(videoElementId);
            if (!this.videoElement) {
                throw new Error('Video-Element nicht gefunden');
            }
            
            // Kamera-Zugriff anfordern
            const constraints = {
                video: {
                    facingMode: 'environment', // Rückkamera bevorzugen
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            };
            
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.videoElement.srcObject = this.stream;
            this.videoElement.play();
            
            return true;
        } catch (error) {
            if (errorCallback) {
                errorCallback('Kamera-Fehler: ' + error.message);
            }
            return false;
        }
    }
    
    // Foto aufnehmen
    capturePhoto(canvasElementId) {
        if (!this.videoElement || !this.stream) {
            return null;
        }
        
        const canvas = document.getElementById(canvasElementId);
        if (!canvas) {
            return null;
        }
        
        // Canvas-Größe an Video anpassen
        canvas.width = this.videoElement.videoWidth;
        canvas.height = this.videoElement.videoHeight;
        
        // Aktuellen Frame auf Canvas zeichnen
        const context = canvas.getContext('2d');
        context.drawImage(this.videoElement, 0, 0);
        
        // Blob erstellen
        return new Promise((resolve) => {
            canvas.toBlob((blob) => {
                resolve(blob);
            }, 'image/jpeg', 0.85);
        });
    }
    
    // Kamera stoppen
    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        if (this.videoElement) {
            this.videoElement.srcObject = null;
        }
    }
    
    // Prüfen ob Kamera verfügbar
    static isAvailable() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
}

// File-Helper für Drag & Drop
class FileDropHelper {
    constructor(dropZoneId, fileInputId, previewContainerId) {
        this.dropZone = document.getElementById(dropZoneId);
        this.fileInput = document.getElementById(fileInputId);
        this.previewContainer = document.getElementById(previewContainerId);
        this.files = [];
        
        this.init();
    }
    
    init() {
        if (!this.dropZone || !this.fileInput) return;
        
        // Drag & Drop Events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, this.preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.remove('drag-over');
            }, false);
        });
        
        this.dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            this.handleFiles(files);
        }, false);
        
        // Click to select
        this.dropZone.addEventListener('click', () => {
            this.fileInput.click();
        });
        
        this.fileInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });
    }
    
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    handleFiles(fileList) {
        const filesArray = Array.from(fileList);
        
        // Nur Bilder erlauben
        const imageFiles = filesArray.filter(file => file.type.startsWith('image/'));
        
        // Zu bestehenden Files hinzufügen
        this.files = this.files.concat(imageFiles);
        
        // Preview aktualisieren
        this.updatePreview();
        
        // DataTransfer für Input erstellen
        this.updateFileInput();
    }
    
    updateFileInput() {
        const dt = new DataTransfer();
        this.files.forEach(file => dt.items.add(file));
        this.fileInput.files = dt.files;
    }
    
    updatePreview() {
        if (!this.previewContainer) return;
        
        this.previewContainer.innerHTML = '';
        
        this.files.forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                const preview = document.createElement('div');
                preview.className = 'image-preview-item';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-image" onclick="removePreviewImage(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                this.previewContainer.appendChild(preview);
            };
            
            reader.readAsDataURL(file);
        });
    }
    
    removeFile(index) {
        this.files.splice(index, 1);
        this.updatePreview();
        this.updateFileInput();
    }
}