/**
 * Multi-Format Video Player with Fallback Support - FIXED VERSION
 * Supports: HLS (.m3u8), MPEG-TS (.ts), MP4, WebM, and other HTML5 video formats
 */

class MultiFormatPlayer {
    constructor(videoElementId = 'the_streamer', modalId = 'vid_modal') {
        this.videoElement = document.getElementById(videoElementId);
        this.modal = UIkit.modal(`#${modalId}`);
        this.currentPlayer = null;
        this.playerType = null;
        this.proxyUrl = '/proxy/stream';
        this.originalUrl = null;
        this.loadingModal = null; // Track loading modal

        this.videoJsPlayer = null;

        // Player states
        this.players = {
            hls: null,
            mpegts: null,
            videojs: null,
            native: null
        };
    }

    /**
     * Main entry point to play a stream
     */
    async play(url, options = {}) {
        console.log('Play method called with URL:', url);

        this.originalUrl = url;
        const useProxy = options.useProxy !== false;
        const streamUrl = useProxy ? this.getProxiedUrl(url) : url;

        // Show loading modal
        this.showLoadingModal();

        this.cleanup();

        const streamType = this.detectStreamType(url);
        console.log(`Attempting to play: ${url}`);
        console.log(`Detected type: ${streamType}`);

        let success = false;

        switch (streamType) {
            case 'hls':
                success = await this.tryHLS(streamUrl) ||
                    await this.tryVideoJS(streamUrl) ||
                    await this.tryNative(streamUrl);
                break;

            case 'mpegts':
                // For MPEG-TS, try mpegts.js first, then try .m3u8 equivalent, then other players
                // Sequential with delays to reduce server load
                success = await this.tryMpegTS(streamUrl);
                if (!success) {
                    await this.delay(300);
                    console.log('mpegts.js failed, trying .m3u8 fallback...');
                    success = await this.tryTsToM3u8Fallback(url, useProxy);
                }
                if (!success) {
                    await this.delay(300);
                    console.log('.m3u8 fallback failed, trying remaining players...');
                    success = await this.tryVideoJS(streamUrl);
                }
                break;

            case 'video':
                success = await this.tryNative(streamUrl) ||
                    await this.tryVideoJS(streamUrl);
                break;

            default:
                // Try players sequentially with delays to reduce server load
                success = await this.tryHLS(streamUrl);
                if (!success) {
                    await this.delay(300);
                    success = await this.tryMpegTS(streamUrl);
                }
                if (!success && url.toLowerCase().includes('.ts')) {
                    await this.delay(300);
                    console.log('Trying .ts to .m3u8 fallback for unknown type...');
                    success = await this.tryTsToM3u8Fallback(url, useProxy);
                }
                if (!success) {
                    await this.delay(300);
                    success = await this.tryVideoJS(streamUrl);
                }
                if (!success) {
                    await this.delay(300);
                    success = await this.tryNative(streamUrl);
                }
                break;
        }

        if (!success) {
            // Hide loading modal on failure
            this.hideLoadingModal();
            this.showFallbackModal();
        } else {
            // Hide loading modal and show video modal
            this.hideLoadingModal();
            this.modal.show();
        }
    }

    /**
     * Detect stream type from URL
     */
    detectStreamType(url) {
        const lower = url.toLowerCase();

        if (lower.includes('.m3u8') || lower.includes('/hls/')) {
            return 'hls';
        } else if (lower.includes('.ts') && !lower.includes('.m3u8')) {
            return 'mpegts';
        } else if (lower.match(/\.(mp4|webm|ogg|mov)$/)) {
            return 'video';
        }

        return 'unknown';
    }

    /**
     * Get proxied URL for CORS bypass
     */
    getProxiedUrl(url) {
        const baseUrl = window.location.origin;
        return `${baseUrl}/proxy/stream?url=${encodeURIComponent(url)}`;
    }

    /**
     * Show loading modal with spinner
     */
    showLoadingModal() {
        // Create loading modal if it doesn't exist
        if (!document.getElementById('loading_modal')) {
            const modalHtml = `
                <div id="loading_modal" uk-modal>
                    <div class="uk-modal-dialog uk-modal-body uk-text-center">
                        <div uk-spinner="ratio: 2"></div>
                        <p class="uk-margin-top">Loading stream...</p>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        this.loadingModal = UIkit.modal('#loading_modal', {
            bgClose: false,
            escClose: false
        });
        this.loadingModal.show();
    }

    /**
     * Hide loading modal
     */
    hideLoadingModal() {
        if (this.loadingModal) {
            this.loadingModal.hide();
            this.loadingModal = null;
        }
    }

    /**
     * Add small delay between player attempts to reduce server load
     */
    async delay(ms = 500) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Try .ts to .m3u8 fallback for streams that might have HLS equivalents
     */
    async tryTsToM3u8Fallback(originalUrl, useProxy) {
        // Only try this for .ts URLs
        if (!originalUrl.toLowerCase().includes('.ts')) {
            return false;
        }

        console.log('Trying .ts to .m3u8 fallback...');

        // Replace .ts with .m3u8
        let m3u8Url = originalUrl.replace(/\.ts$/i, '.m3u8');

        if (m3u8Url === originalUrl) {
            // URL didn't end with .ts, try replacing .ts anywhere in the URL
            m3u8Url = originalUrl.replace(/\.ts/gi, '.m3u8');
            if (m3u8Url === originalUrl) {
                return false; // No .ts found to replace
            }
        }

        const streamUrl = useProxy ? this.getProxiedUrl(m3u8Url) : m3u8Url;
        console.log(`Trying HLS equivalent: ${m3u8Url}`);

        // Try HLS.js with the .m3u8 equivalent
        return await this.tryHLS(streamUrl);
    }

    /**
     * Try HLS.js player
     */
    async tryHLS(url) {
        if (!window.Hls || !Hls.isSupported()) {
            console.log('HLS.js not supported');
            return false;
        }

        return new Promise((resolve) => {
            console.log('Trying HLS.js...');

            const hls = new Hls({
                debug: false,
                enableWorker: true,
                lowLatencyMode: true,
                backBufferLength: 90
            });

            this.players.hls = hls;

            hls.on(Hls.Events.MANIFEST_PARSED, () => {
                console.log('HLS.js: Manifest parsed successfully');
                this.videoElement.play().then(() => {
                    this.currentPlayer = 'hls';
                    this.hideLoadingModal(); // Hide loading when video actually starts
                    resolve(true);
                }).catch(e => {
                    console.error('HLS.js: Play failed', e);
                    this.safeDestroyHLS();
                    resolve(false);
                });
            });

            hls.on(Hls.Events.ERROR, (event, data) => {
                console.error('HLS.js error:', data);
                if (data.fatal) {
                    this.safeDestroyHLS();
                    resolve(false);
                }
            });

            hls.loadSource(url);
            hls.attachMedia(this.videoElement);

            setTimeout(() => {
                if (!this.currentPlayer) {
                    this.safeDestroyHLS();
                    resolve(false);
                }
            }, 5000);
        });
    }

    /**
     * Try mpegts.js player - FIXED VERSION
     */
    async tryMpegTS(url) {
        if (!window.mpegts || !mpegts.isSupported()) {
            console.log('mpegts.js not supported');
            return false;
        }

        return new Promise((resolve) => {
            console.log('Trying mpegts.js...');

            try {
                const player = mpegts.createPlayer({
                    type: 'mse',
                    isLive: true,
                    url: url,
                    hasAudio: true,
                    hasVideo: true,
                }, {
                    enableStashBuffer: false,
                    stashInitialSize: 128,
                    enableWorker: true,
                    lazyLoadMaxDuration: 3 * 60,
                    seekType: 'range'
                });

                this.players.mpegts = player;

                player.on(mpegts.Events.ERROR, (e) => {
                    console.error('mpegts.js error:', e);
                    this.safePauseMpegTS(); // Use safe cleanup
                    this.players.mpegts = null; // Clear reference
                    resolve(false);
                });

                player.on(mpegts.Events.MEDIA_INFO, (mediaInfo) => {
                    console.log('mpegts.js: Media info received', mediaInfo);
                });

                player.on(mpegts.Events.LOADSTART, () => {
                    console.log('mpegts.js: Load started');
                    this.videoElement.play().then(() => {
                        this.currentPlayer = 'mpegts';
                        this.hideLoadingModal(); // Hide loading when video actually starts
                        resolve(true);
                    }).catch(e => {
                        console.error('mpegts.js: Play failed', e);
                        this.safePauseMpegTS();
                        this.players.mpegts = null;
                        resolve(false);
                    });
                });

                player.attachMediaElement(this.videoElement);
                player.load();
                player.play();

                setTimeout(() => {
                    if (!this.currentPlayer) {
                        this.safePauseMpegTS();
                        this.players.mpegts = null;
                        resolve(false);
                    }
                }, 5000);

            } catch (error) {
                console.error('mpegts.js initialization failed:', error);
                this.players.mpegts = null;
                resolve(false);
            }
        });
    }

    /**
     * Try Video.js player
     */
    async tryVideoJS(url) {
        if (!window.videojs) {
            console.log('Video.js not available');
            return false;
        }

        return new Promise((resolve) => {
            console.log('Trying Video.js...');

            try {
                if (!this.videoJsPlayer) {
                    this.videoJsPlayer = videojs(this.videoElement.id, {
                        controls: true,
                        autoplay: false,
                        preload: 'auto',
                        fluid: true,
                        liveui: true,
                        html5: {
                            vhs: {
                                overrideNative: true,
                                smoothQualityChange: true,
                                fastQualityChange: true
                            }
                        }
                    });
                }

                this.players.videojs = this.videoJsPlayer;

                this.videoJsPlayer.ready(() => {
                    this.videoJsPlayer.src({
                        src: url,
                        type: this.getVideoJsType(url)
                    });

                    this.videoJsPlayer.one('loadedmetadata', () => {
                        console.log('Video.js: Metadata loaded');
                        this.videoJsPlayer.play().then(() => {
                            this.currentPlayer = 'videojs';
                            this.hideLoadingModal(); // Hide loading when video actually starts
                            resolve(true);
                        }).catch(e => {
                            console.error('Video.js: Play failed', e);
                            resolve(false);
                        });
                    });

                    this.videoJsPlayer.one('error', () => {
                        console.error('Video.js: Error loading');
                        resolve(false);
                    });

                    setTimeout(() => {
                        if (!this.currentPlayer) {
                            resolve(false);
                        }
                    }, 5000);
                });

            } catch (error) {
                console.error('Video.js initialization failed:', error);
                resolve(false);
            }
        });
    }

    /**
     * Try native HTML5 video
     */
    async tryNative(url) {
        return new Promise((resolve) => {
            console.log('Trying native HTML5 video...');

            // Don't try native for MPEG-TS files
            if (this.detectStreamType(url) === 'mpegts') {
                console.log('Skipping native player for MPEG-TS format');
                resolve(false);
                return;
            }

            try {
                if (this.videoJsPlayer) {
                    this.videoJsPlayer.dispose();
                    this.videoJsPlayer = null;

                    // Check if video element still has a parent
                    const parent = this.videoElement.parentNode;
                    if (parent) {
                        const newVideo = document.createElement('video');
                        newVideo.id = this.videoElement.id;
                        newVideo.className = this.videoElement.className;
                        newVideo.controls = true;
                        newVideo.width = 800;
                        newVideo.height = 450;
                        parent.replaceChild(newVideo, this.videoElement);
                        this.videoElement = newVideo;
                    } else {
                        // Video element was removed, try to find it again or recreate
                        const originalId = this.videoElement.id;
                        this.videoElement = document.getElementById(originalId);

                        if (!this.videoElement) {
                            console.error('Native player: Video element not found');
                            resolve(false);
                            return;
                        }
                    }
                }

                this.videoElement.src = url;

                const playHandler = () => {
                    console.log('Native player: Playing');
                    this.currentPlayer = 'native';
                    this.hideLoadingModal(); // Hide loading when video actually starts
                    cleanup();
                    resolve(true);
                };

                const errorHandler = () => {
                    console.error('Native player: Error');
                    cleanup();
                    resolve(false);
                };

                const cleanup = () => {
                    this.videoElement.removeEventListener('canplay', playHandler);
                    this.videoElement.removeEventListener('error', errorHandler);
                };

                this.videoElement.addEventListener('canplay', playHandler, { once: true });
                this.videoElement.addEventListener('error', errorHandler, { once: true });

                this.videoElement.load();

                setTimeout(() => {
                    if (!this.currentPlayer) {
                        cleanup();
                        resolve(false);
                    }
                }, 5000);

            } catch (error) {
                console.error('Native player initialization failed:', error);
                resolve(false);
            }
        });
    }

    /**
     * Get Video.js type from URL - IMPROVED VERSION
     */
    getVideoJsType(url) {
        const lower = url.toLowerCase();

        if (lower.includes('.m3u8')) return 'application/x-mpegURL';
        if (lower.includes('.mp4')) return 'video/mp4';
        if (lower.includes('.webm')) return 'video/webm';
        if (lower.includes('.ogg')) return 'video/ogg';

        // For .ts files, try HLS type first - Video.js can sometimes handle them
        if (lower.includes('.ts')) return 'application/x-mpegURL';

        return 'application/x-mpegURL'; // Default to HLS
    }

    /**
     * Safe cleanup for HLS.js
     */
    safeDestroyHLS() {
        if (this.players.hls) {
            try {
                this.players.hls.destroy();
            } catch (e) {
                console.error('Error destroying HLS player:', e);
            }
            this.players.hls = null;
        }
    }

    /**
     * Safe cleanup for mpegts.js - FIXED VERSION
     */
    safePauseMpegTS() {
        if (this.players.mpegts) {
            try {
                // Check if player has the methods before calling them
                if (typeof this.players.mpegts.pause === 'function') {
                    this.players.mpegts.pause();
                }
                if (typeof this.players.mpegts.unload === 'function') {
                    this.players.mpegts.unload();
                }
                if (typeof this.players.mpegts.detachMediaElement === 'function') {
                    this.players.mpegts.detachMediaElement();
                }
                if (typeof this.players.mpegts.destroy === 'function') {
                    this.players.mpegts.destroy();
                }
            } catch (e) {
                console.error('Error cleaning up mpegts player:', e);
            }
        }
    }

    /**
     * Show fallback modal with VLC option
     */
    showFallbackModal() {
        console.error('All players failed');

        const streamType = this.detectStreamType(this.originalUrl);
        let additionalInfo = '';

        if (streamType === 'mpegts') {
            additionalInfo = '<p><strong>Note:</strong> This appears to be an MPEG-TS stream, which requires special player support.</p>';
        }

        UIkit.modal.alert(`
            <div class="uk-text-center">
                <h3 class="uk-text-center">Unable to Play Stream</h3>
                <p>All web players failed to load this stream.</p>
                ${additionalInfo}
                <p>You can try playing it in VLC Media Player:</p>
                <div class="uk-margin">
                    <input type="text" class="uk-input" value="${this.originalUrl}" readonly>
                </div>
                <p class="uk-text-center">Copy the URL above and paste it into VLC:<br>
                Media → Open Network Stream</p>
            </div>
        `);
    }

    /**
     * Clean up all players - FIXED VERSION
     */
    cleanup() {
        console.log('Cleaning up players...');

        // DON'T hide loading modal here - let it stay until success/failure

        // Clean HLS.js
        this.safeDestroyHLS();

        // Clean mpegts.js with safe cleanup
        this.safePauseMpegTS();
        this.players.mpegts = null;

        // Clean Video.js
        if (this.videoJsPlayer) {
            try {
                this.videoJsPlayer.pause();
                this.videoJsPlayer.reset();
            } catch (e) {
                console.error('Error cleaning up Video.js:', e);
            }
        }

        // Reset native video
        try {
            if (this.videoElement && typeof this.videoElement.load === 'function') {
                this.videoElement.src = '';
                this.videoElement.load();
            }
        } catch (e) {
            console.error('Error resetting native video:', e);
        }

        this.currentPlayer = null;
    }

    /**
     * Stop playback and close modal
     */
    stop() {
        this.cleanup();
        this.hideLoadingModal();
        this.modal.hide();
    }
}

// Initialize the player when DOM is ready
let multiPlayer;

document.addEventListener('DOMContentLoaded', function () {
    multiPlayer = new MultiFormatPlayer('the_streamer', 'vid_modal');

    UIkit.util.on('#vid_modal', 'hidden', function () {
        if (multiPlayer) {
            multiPlayer.cleanup();
        }
    });
});

function playStream(url, useProxy = true) {
    if (!multiPlayer) {
        multiPlayer = new MultiFormatPlayer('the_streamer', 'vid_modal');
    }

    multiPlayer.play(url, { useProxy: useProxy });
}

document.addEventListener('click', function (e) {
    const streamElement = e.target.closest('.play-stream');

    if (streamElement) {
        e.preventDefault();
        const url = streamElement.getAttribute('data-stream-url');

        if (url) {
            playStream(url);
        } else {
            console.error('No data-stream-url attribute found on element');
        }
    }
});