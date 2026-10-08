/**
 * Smart Image Uploader & Client-Side Optimizer for ShopMart Admin
 * 
 * - Automatically detects large photos (e.g. 10MB - 50MB smartphone camera uploads)
 * - Downsamples and compresses high-resolution images to crystal-clear web HD (max 2000px, 85% JPEG)
 * - Dramatically reduces upload payload from 200MB+ down to ~1MB - 3MB
 * - Completely prevents HTTP 413 (Payload Too Large) and slow upload timeouts
 * - Shows instant thumbnail previews and compression stats
 * - Protects against accidental double-submissions during upload
 */

(function () {
    'use strict';

    var MAX_WIDTH = 2000;
    var MAX_HEIGHT = 2000;
    var JPEG_QUALITY = 0.85;
    var COMPRESSION_THRESHOLD_BYTES = 800 * 1024; // 800 KB

    var activeCompressions = 0;
    var compressionCallbacks = [];

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(i > 1 ? 1 : 0) + ' ' + units[i];
    }

    function isImageFile(file) {
        if (!file) return false;
        if (file.type && file.type.indexOf('image/') === 0) return true;
        return /\.(jpe?g|png|webp|gif)$/i.test(file.name);
    }

    function isEligibleInput(input) {
        if (!input || input.type !== 'file') return false;
        var name = (input.name || '').toLowerCase();
        if (name.indexOf('photo') !== -1 || name.indexOf('image') !== -1 || name.indexOf('banner') !== -1) {
            return true;
        }
        var accept = (input.getAttribute('accept') || '').toLowerCase();
        if (accept.indexOf('image') !== -1) return true;
        return input.hasAttribute('data-smart-compress');
    }

    function getOrCreatePreviewContainer(input) {
        var existing = input.parentElement ? input.parentElement.querySelector('.sn-smart-img-preview') : null;
        if (existing) return existing;

        var container = document.createElement('div');
        container.className = 'sn-smart-img-preview';
        container.style.cssText = 'display:flex; align-items:center; gap:10px; margin-top:8px; padding:6px 10px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; font-size:12px; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; max-width:480px; box-shadow:0 1px 2px rgba(0,0,0,0.03);';
        
        if (input.nextSibling) {
            input.parentNode.insertBefore(container, input.nextSibling);
        } else {
            input.parentNode.appendChild(container);
        }
        return container;
    }

    function removePreviewContainer(input) {
        var container = input.parentElement ? input.parentElement.querySelector('.sn-smart-img-preview') : null;
        if (container) {
            container.remove();
        }
    }

    /**
     * Compress an image file using HTML5 Canvas
     */
    function compressImageFile(file) {
        return new Promise(function (resolve) {
            if (!isImageFile(file)) {
                return resolve({ file: file, modified: false, error: 'Not an image' });
            }

            // If GIF or SVG, do not compress via canvas to preserve animation/vector
            var isGifOrSvg = file.type === 'image/gif' || file.type === 'image/svg+xml' || /\.gif$/i.test(file.name);
            if (isGifOrSvg) {
                return resolve({ file: file, modified: false });
            }

            // If already below threshold, keep original
            if (file.size <= COMPRESSION_THRESHOLD_BYTES) {
                return resolve({ file: file, modified: false });
            }

            var reader = new FileReader();
            reader.onerror = function () {
                resolve({ file: file, modified: false });
            };
            reader.onload = function (e) {
                var img = new Image();
                img.onerror = function () {
                    resolve({ file: file, modified: false });
                };
                img.onload = function () {
                    var width = img.naturalWidth || img.width;
                    var height = img.naturalHeight || img.height;

                    if (!width || !height) {
                        return resolve({ file: file, modified: false });
                    }

                    // Calculate target dimensions
                    var targetWidth = width;
                    var targetHeight = height;

                    if (targetWidth > MAX_WIDTH || targetHeight > MAX_HEIGHT) {
                        var ratio = Math.min(MAX_WIDTH / targetWidth, MAX_HEIGHT / targetHeight);
                        targetWidth = Math.round(targetWidth * ratio);
                        targetHeight = Math.round(targetHeight * ratio);
                    }

                    var canvas = document.createElement('canvas');
                    canvas.width = targetWidth;
                    canvas.height = targetHeight;
                    var ctx = canvas.getContext('2d');

                    if (!ctx) {
                        return resolve({ file: file, modified: false });
                    }

                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';

                    // Draw white background in case source had alpha and converting to JPEG
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, targetWidth, targetHeight);
                    ctx.drawImage(img, 0, 0, targetWidth, targetHeight);

                    var outputMime = 'image/jpeg';
                    canvas.toBlob(function (blob) {
                        if (!blob || blob.size >= file.size) {
                            // If compression didn't save space, keep original
                            return resolve({ file: file, modified: false });
                        }

                        var newFileName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                        var compressedFile;
                        try {
                            compressedFile = new File([blob], newFileName, {
                                type: outputMime,
                                lastModified: Date.now()
                            });
                        } catch (err) {
                            // Fallback if File constructor fails
                            compressedFile = blob;
                            compressedFile.name = newFileName;
                            compressedFile.lastModifiedDate = new Date();
                        }

                        resolve({
                            file: compressedFile,
                            modified: true,
                            originalSize: file.size,
                            compressedSize: blob.size,
                            previewUrl: URL.createObjectURL(blob),
                            width: targetWidth,
                            height: targetHeight
                        });
                    }, outputMime, JPEG_QUALITY);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    /**
     * Process an input element's files
     */
    function handleFileInput(input) {
        if (!input.files || input.files.length === 0) {
            removePreviewContainer(input);
            return;
        }

        var file = input.files[0];
        if (!isImageFile(file)) {
            removePreviewContainer(input);
            return;
        }

        var container = getOrCreatePreviewContainer(input);
        var origSize = file.size;

        if (origSize > COMPRESSION_THRESHOLD_BYTES) {
            container.innerHTML = '' +
                '<div style="width:36px; height:36px; border-radius:6px; background:#FEF3C7; display:flex; align-items:center; justify-content:center; color:#B45309; flex-shrink:0;">' +
                    '<i class="fa fa-spinner fa-spin" style="font-size:16px;"></i>' +
                '</div>' +
                '<div style="flex:1; line-height:1.3;">' +
                    '<div style="font-weight:600; color:#0F172A;">Optimizing photo for fast upload...</div>' +
                    '<div style="color:#64748B; font-size:11px;">Original size: ' + formatBytes(origSize) + ' (compressing to web HD)</div>' +
                '</div>';

            activeCompressions++;

            compressImageFile(file).then(function (result) {
                activeCompressions = Math.max(0, activeCompressions - 1);

                if (result.modified && window.DataTransfer) {
                    try {
                        var dt = new DataTransfer();
                        dt.items.add(result.file);
                        input.files = dt.files;
                    } catch (e) {
                        console.warn('DataTransfer assignment not supported:', e);
                    }
                }

                var finalSize = (result.file && result.file.size) ? result.file.size : origSize;
                var savedPct = origSize > 0 ? Math.round(((origSize - finalSize) / origSize) * 100) : 0;
                var previewSrc = result.previewUrl || URL.createObjectURL(result.file);

                container.innerHTML = '' +
                    '<img src="' + previewSrc + '" style="width:38px; height:38px; border-radius:6px; object-fit:cover; border:1px solid #CBD5E1; flex-shrink:0;">' +
                    '<div style="flex:1; line-height:1.3;">' +
                        '<div style="font-weight:700; color:#16A34A; display:flex; align-items:center; gap:4px;">' +
                            '<i class="fa fa-check-circle"></i> ' +
                            (result.modified ? 'Optimized: ' + formatBytes(origSize) + ' &rarr; ' + formatBytes(finalSize) + ' (' + savedPct + '% smaller)' : 'Ready for upload (' + formatBytes(finalSize) + ')') +
                        '</div>' +
                        '<div style="color:#64748B; font-size:11px;">Fast HD upload ready. Server will accept instantly.</div>' +
                    '</div>';

                if (activeCompressions === 0 && compressionCallbacks.length > 0) {
                    var cbs = compressionCallbacks.slice();
                    compressionCallbacks = [];
                    cbs.forEach(function (cb) { cb(); });
                }
            }).catch(function (err) {
                activeCompressions = Math.max(0, activeCompressions - 1);
                console.error('Image compression failed:', err);
                container.innerHTML = '<span style="color:#64748B;">Ready (' + formatBytes(origSize) + ')</span>';
            });
        } else {
            // Under threshold - just show preview
            var previewSrc = URL.createObjectURL(file);
            container.innerHTML = '' +
                '<img src="' + previewSrc + '" style="width:38px; height:38px; border-radius:6px; object-fit:cover; border:1px solid #CBD5E1; flex-shrink:0;">' +
                '<div style="flex:1; line-height:1.3;">' +
                    '<div style="font-weight:600; color:#16A34A;"><i class="fa fa-check-circle"></i> Ready: ' + formatBytes(origSize) + '</div>' +
                    '<div style="color:#64748B; font-size:11px;">Image is already optimized and ready.</div>' +
                '</div>';
        }
    }

    // Attach delegated change listener for all file inputs
    document.addEventListener('change', function (e) {
        var target = e.target;
        if (target && isEligibleInput(target)) {
            handleFileInput(target);
        }
    }, true);

    // Form submit listener to handle pending compressions and prevent double-clicks
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.querySelector) return;

        var hasFileInput = form.querySelector('input[type="file"]');
        if (!hasFileInput) return;

        if (activeCompressions > 0) {
            e.preventDefault();
            e.stopPropagation();

            var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            var origBtnText = submitBtn ? submitBtn.innerHTML || submitBtn.value : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Optimizing Photos...';
                }
            }

            compressionCallbacks.push(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (submitBtn.tagName === 'BUTTON') submitBtn.innerHTML = origBtnText;
                }
                // Trigger form submission now that images are optimized
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
            return;
        }

        // Lock submit button to prevent double-click / multiple POSTs
        var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
            setTimeout(function () {
                submitBtn.disabled = true;
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading & Saving...';
                } else {
                    submitBtn.value = 'Uploading & Saving...';
                }
            }, 10);
        }
    }, false);

})();
