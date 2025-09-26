/**
 * Dating App JavaScript Modules
 * Modern ES6+ with Fetch API and mobile-first interactions
 */

// API Base Configuration
const API_CONFIG = {
  baseUrl: window.location.origin,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
};

// Theme Management Module
const ThemeManager = {
  /**
   * Initialize theme on page load
   */
  init() {
    // Force remove any existing theme classes first
    document.documentElement.classList.remove('dark-theme', 'light-theme');
    
    this.loadUserTheme();
    this.initializeToggle();
  },

  /**
   * Load user's theme preference
   */
  loadUserTheme() {
    // Always start with light theme (white background) as default
    this.setTheme('light');
    
    // Then check for saved theme preference
    const savedTheme = localStorage.getItem('theme-preference');
    if (savedTheme && savedTheme === 'dark') {
      this.setTheme('dark');
    }
  },

  /**
   * Set theme and update UI
   * @param {string} theme - 'light' or 'dark'
   */
  setTheme(theme) {
    const root = document.documentElement;
    
    if (theme === 'dark') {
      root.classList.remove('light-theme');
      root.classList.add('dark-theme');
    } else {
      root.classList.remove('dark-theme');
      root.classList.add('light-theme');
    }
    
    // Save preference locally
    localStorage.setItem('theme-preference', theme);
    
    // Update toggle button state
    this.updateToggleButton(theme);
  },

  /**
   * Toggle between light and dark themes
   */
  toggleTheme() {
    const root = document.documentElement;
    const currentTheme = root.classList.contains('dark-theme') ? 'dark' : 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    this.setTheme(newTheme);
    this.saveUserThemePreference(newTheme);
  },

  /**
   * Update toggle button appearance
   * @param {string} theme - Current theme
   */
  updateToggleButton(theme) {
    const toggles = document.querySelectorAll('.theme-toggle');
    toggles.forEach(toggle => {
      const slider = toggle.querySelector('.theme-toggle-slider');
      if (slider) {
        if (theme === 'dark') {
          toggle.setAttribute('aria-label', 'Switch to light mode');
          toggle.setAttribute('title', 'Switch to light mode');
        } else {
          toggle.setAttribute('aria-label', 'Switch to dark mode');
          toggle.setAttribute('title', 'Switch to dark mode');
        }
      }
    });
  },

  /**
   * Initialize theme toggle buttons
   */
  initializeToggle() {
    document.addEventListener('click', (e) => {
      if (e.target.closest('.theme-toggle')) {
        e.preventDefault();
        this.toggleTheme();
      }
    });
  },

  /**
   * Save theme preference to server
   * @param {string} theme - Theme preference
   */
  async saveUserThemePreference(theme) {
    try {
      const formData = new FormData();
      formData.append('action', 'update_theme');
      formData.append('theme', theme);
      formData.append('csrf_token', Utils.getCSRFToken());

      const response = await fetch('/app/profile.php', {
        method: 'POST',
        body: formData
      });

      if (!response.ok) {
        console.warn('Failed to save theme preference to server');
      }
    } catch (error) {
      console.warn('Error saving theme preference:', error);
    }
  }
};

// Utility Functions Module
const Utils = {
  /**
   * Get CSRF token from meta tag or form
   * @returns {string} CSRF token
   */
  getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
  },

  /**
   * Sanitize HTML to prevent XSS
   * @param {string} str - String to sanitize
   * @returns {string} Sanitized string
   */
  sanitizeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  },

  /**
   * Format date for display
   * @param {string|Date} date - Date to format
   * @param {string} format - Format type ('relative' or 'absolute')
   * @returns {string} Formatted date
   */
  formatDate(date, format = 'relative') {
    const d = new Date(date);
    const now = new Date();
    
    if (format === 'relative') {
      const diffMs = now - d;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMins / 60);
      const diffDays = Math.floor(diffHours / 24);
      
      if (diffMins < 1) return 'Just now';
      if (diffMins < 60) return `${diffMins}m ago`;
      if (diffHours < 24) return `${diffHours}h ago`;
      if (diffDays < 7) return `${diffDays}d ago`;
      return d.toLocaleDateString();
    }
    
    return d.toLocaleString();
  },

  /**
   * Debounce function calls
   * @param {Function} func - Function to debounce
   * @param {number} wait - Wait time in ms
   * @returns {Function} Debounced function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  /**
   * Check if device supports touch
   * @returns {boolean} True if touch is supported
   */
  isTouchDevice() {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  },

  /**
   * Generate unique ID
   * @returns {string} Unique ID
   */
  generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
  }
};

// HTTP Client Module
const Http = {
  /**
   * Make HTTP request with modern fetch API
   * @param {string} url - Request URL
   * @param {Object} options - Request options
   * @returns {Promise} Response promise
   */
  async request(url, options = {}) {
    const config = {
      ...API_CONFIG,
      ...options,
      headers: {
        ...API_CONFIG.headers,
        ...options.headers
      }
    };

    // Add CSRF token to POST/PUT/DELETE requests
    if (['POST', 'PUT', 'DELETE'].includes(config.method?.toUpperCase())) {
      const token = Utils.getCSRFToken();
      if (token) {
        if (config.body instanceof FormData) {
          config.body.append('csrf_token', token);
        } else if (typeof config.body === 'string') {
          const data = new URLSearchParams(config.body);
          data.append('csrf_token', token);
          config.body = data.toString();
          config.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
      }
    }

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), config.timeout);
      
      const response = await fetch(url, {
        ...config,
        signal: controller.signal
      });
      
      clearTimeout(timeoutId);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      return response;
    } catch (error) {
      if (error.name === 'AbortError') {
        throw new Error('Request timeout');
      }
      throw error;
    }
  },

  /**
   * GET request
   * @param {string} url - Request URL
   * @param {Object} options - Request options
   * @returns {Promise} Response promise
   */
  async get(url, options = {}) {
    return this.request(url, { ...options, method: 'GET' });
  },

  /**
   * POST request
   * @param {string} url - Request URL
   * @param {Object|FormData|string} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise} Response promise
   */
  async post(url, data = {}, options = {}) {
    let body = data;
    
    if (!(data instanceof FormData) && typeof data === 'object') {
      body = new URLSearchParams(data).toString();
    }
    
    return this.request(url, {
      ...options,
      method: 'POST',
      body
    });
  },

  /**
   * PUT request
   * @param {string} url - Request URL
   * @param {Object} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise} Response promise
   */
  async put(url, data = {}, options = {}) {
    return this.request(url, {
      ...options,
      method: 'PUT',
      body: JSON.stringify(data),
      headers: { 'Content-Type': 'application/json' }
    });
  },

  /**
   * DELETE request
   * @param {string} url - Request URL
   * @param {Object} options - Request options
   * @returns {Promise} Response promise
   */
  async delete(url, options = {}) {
    return this.request(url, { ...options, method: 'DELETE' });
  }
};

// Flash Message System
const Flash = {
  container: null,

  /**
   * Initialize flash message system
   */
  init() {
    this.container = document.querySelector('.flash-container') || this.createContainer();
  },

  /**
   * Create flash message container
   * @returns {HTMLElement} Container element
   */
  createContainer() {
    const container = document.createElement('div');
    container.className = 'flash-container';
    container.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      max-width: 400px;
    `;
    document.body.appendChild(container);
    return container;
  },

  /**
   * Show flash message
   * @param {string} message - Message text
   * @param {string} type - Message type (success, error, warning, info)
   * @param {number} duration - Auto-hide duration in ms
   */
  show(message, type = 'info', duration = 5000) {
    if (!this.container) this.init();

    const flash = document.createElement('div');
    flash.className = `flash-message flash-${type} animate-slide-in-up`;
    flash.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <span>${Utils.sanitizeHTML(message)}</span>
        <button type="button" style="background: none; border: none; font-size: 1.2em; cursor: pointer; margin-left: 10px;">&times;</button>
      </div>
    `;

    // Add close functionality
    const closeBtn = flash.querySelector('button');
    closeBtn.addEventListener('click', () => this.remove(flash));

    this.container.appendChild(flash);

    // Auto-remove after duration
    if (duration > 0) {
      setTimeout(() => this.remove(flash), duration);
    }

    return flash;
  },

  /**
   * Remove flash message
   * @param {HTMLElement} flash - Flash message element
   */
  remove(flash) {
    if (flash && flash.parentNode) {
      flash.style.animation = 'fadeOut 0.3s ease-out';
      setTimeout(() => flash.remove(), 300);
    }
  },

  /**
   * Show success message
   * @param {string} message - Message text
   */
  success(message) {
    return this.show(message, 'success');
  },

  /**
   * Show error message
   * @param {string} message - Message text
   */
  error(message) {
    return this.show(message, 'error');
  },

  /**
   * Show warning message
   * @param {string} message - Message text
   */
  warning(message) {
    return this.show(message, 'warning');
  },

  /**
   * Show info message
   * @param {string} message - Message text
   */
  info(message) {
    return this.show(message, 'info');
  }
};

// Form Handler Module
const FormHandler = {
  /**
   * Enhanced form submission with loading states and validation
   * @param {HTMLFormElement} form - Form element
   * @param {Object} options - Configuration options
   */
  async submit(form, options = {}) {
    const {
      onSubmit = () => {},
      onSuccess = () => {},
      onError = (error) => Flash.error(error.message),
      onFinally = () => {},
      validateBeforeSubmit = true,
      showLoading = true
    } = options;

    try {
      // Basic validation
      if (validateBeforeSubmit && !form.checkValidity()) {
        form.reportValidity();
        return false;
      }

      // Show loading state
      if (showLoading) {
        this.setLoadingState(form, true);
      }

      // Execute onSubmit callback
      await onSubmit();

      // Prepare form data
      const formData = new FormData(form);
      const url = form.action || window.location.href;
      const method = (form.method || 'POST').toUpperCase();

      // Submit form
      const response = await Http.request(url, {
        method,
        body: formData
      });

      // Handle response
      if (response.headers.get('content-type')?.includes('application/json')) {
        const result = await response.json();
        await onSuccess(result, response);
      } else {
        // Handle redirect or HTML response
        const text = await response.text();
        if (response.redirected || text.includes('<html')) {
          window.location.href = response.url || form.action;
        } else {
          await onSuccess({ message: 'Success' }, response);
        }
      }

      return true;
    } catch (error) {
      console.error('Form submission error:', error);
      onError(error);
      return false;
    } finally {
      if (showLoading) {
        this.setLoadingState(form, false);
      }
      onFinally();
    }
  },

  /**
   * Set form loading state
   * @param {HTMLFormElement} form - Form element
   * @param {boolean} loading - Loading state
   */
  setLoadingState(form, loading) {
    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
    const inputs = form.querySelectorAll('input, select, textarea, button');

    if (loading) {
      form.classList.add('loading');
      inputs.forEach(input => input.disabled = true);
      
      if (submitBtn) {
        submitBtn.dataset.originalText = submitBtn.textContent;
        submitBtn.innerHTML = '<span class="spinner"></span> Loading...';
      }
    } else {
      form.classList.remove('loading');
      inputs.forEach(input => input.disabled = false);
      
      if (submitBtn && submitBtn.dataset.originalText) {
        submitBtn.textContent = submitBtn.dataset.originalText;
        delete submitBtn.dataset.originalText;
      }
    }
  }
};

// Touch/Swipe Handler for Dating Interface
const SwipeHandler = {
  startX: 0,
  startY: 0,
  currentX: 0,
  currentY: 0,
  isDragging: false,
  threshold: 100, // Minimum distance for swipe
  restraint: 100, // Maximum perpendicular distance

  /**
   * Initialize swipe handling on element
   * @param {HTMLElement} element - Element to handle swipes on
   * @param {Object} callbacks - Swipe event callbacks
   */
  init(element, callbacks = {}) {
    const {
      onSwipeLeft = () => {},
      onSwipeRight = () => {},
      onSwipeUp = () => {},
      onSwipeDown = () => {},
      onDragStart = () => {},
      onDragMove = () => {},
      onDragEnd = () => {}
    } = callbacks;

    // Touch events
    element.addEventListener('touchstart', (e) => {
      this.handleStart(e.touches[0], onDragStart);
    }, { passive: true });

    element.addEventListener('touchmove', (e) => {
      this.handleMove(e.touches[0], onDragMove);
    }, { passive: true });

    element.addEventListener('touchend', (e) => {
      this.handleEnd(onSwipeLeft, onSwipeRight, onSwipeUp, onSwipeDown, onDragEnd);
    }, { passive: true });

    // Mouse events for desktop testing
    element.addEventListener('mousedown', (e) => {
      this.handleStart(e, onDragStart);
    });

    element.addEventListener('mousemove', (e) => {
      if (this.isDragging) {
        this.handleMove(e, onDragMove);
      }
    });

    element.addEventListener('mouseup', (e) => {
      if (this.isDragging) {
        this.handleEnd(onSwipeLeft, onSwipeRight, onSwipeUp, onSwipeDown, onDragEnd);
      }
    });

    // Prevent context menu on touch devices
    element.addEventListener('contextmenu', (e) => e.preventDefault());
  },

  /**
   * Handle start of touch/drag
   * @param {Touch|MouseEvent} point - Touch or mouse event
   * @param {Function} onDragStart - Drag start callback
   */
  handleStart(point, onDragStart) {
    this.startX = this.currentX = point.clientX;
    this.startY = this.currentY = point.clientY;
    this.isDragging = true;
    onDragStart({ x: this.startX, y: this.startY });
  },

  /**
   * Handle touch/drag movement
   * @param {Touch|MouseEvent} point - Touch or mouse event
   * @param {Function} onDragMove - Drag move callback
   */
  handleMove(point, onDragMove) {
    if (!this.isDragging) return;

    this.currentX = point.clientX;
    this.currentY = point.clientY;

    const deltaX = this.currentX - this.startX;
    const deltaY = this.currentY - this.startY;

    onDragMove({ x: this.currentX, y: this.currentY, deltaX, deltaY });
  },

  /**
   * Handle end of touch/drag
   * @param {Function} onSwipeLeft - Swipe left callback
   * @param {Function} onSwipeRight - Swipe right callback
   * @param {Function} onSwipeUp - Swipe up callback
   * @param {Function} onSwipeDown - Swipe down callback
   * @param {Function} onDragEnd - Drag end callback
   */
  handleEnd(onSwipeLeft, onSwipeRight, onSwipeUp, onSwipeDown, onDragEnd) {
    if (!this.isDragging) return;

    const deltaX = this.currentX - this.startX;
    const deltaY = this.currentY - this.startY;
    const absDeltaX = Math.abs(deltaX);
    const absDeltaY = Math.abs(deltaY);

    // Determine swipe direction
    if (absDeltaX >= this.threshold && absDeltaY <= this.restraint) {
      if (deltaX > 0) {
        onSwipeRight();
      } else {
        onSwipeLeft();
      }
    } else if (absDeltaY >= this.threshold && absDeltaX <= this.restraint) {
      if (deltaY > 0) {
        onSwipeDown();
      } else {
        onSwipeUp();
      }
    }

    onDragEnd({ deltaX, deltaY });
    this.isDragging = false;
  }
};

// Dating App Specific Modules
const DatingApp = {
  currentUser: null,
  currentProfile: null,
  profiles: [],
  profileIndex: 0,

  /**
   * Initialize dating app
   */
  async init() {
    Flash.init();
    await this.loadCurrentUser();
    this.initializeEventListeners();
  },

  /**
   * Load current user data
   */
  async loadCurrentUser() {
    try {
      const response = await Http.get('/api/user');
      if (response.ok) {
        this.currentUser = await response.json();
      }
    } catch (error) {
      console.error('Failed to load user:', error);
    }
  },

  /**
   * Initialize global event listeners
   */
  initializeEventListeners() {
    // Form submissions
    document.addEventListener('submit', (e) => {
      if (e.target.classList.contains('ajax-form')) {
        e.preventDefault();
        FormHandler.submit(e.target);
      }
    });

    // Navigation
    document.addEventListener('click', (e) => {
      if (e.target.matches('[data-action]')) {
        this.handleAction(e.target.dataset.action, e.target);
      }
    });

    // Auto-logout warning
    this.setupSessionTimeout();
  },

  /**
   * Handle data-action clicks
   * @param {string} action - Action to perform
   * @param {HTMLElement} element - Element that triggered action
   */
  async handleAction(action, element) {
    switch (action) {
      case 'like':
        await this.likeProfile(element.dataset.userId);
        break;
      case 'pass':
        await this.passProfile(element.dataset.userId);
        break;
      case 'logout':
        await this.logout();
        break;
      case 'next-profile':
        this.nextProfile();
        break;
      case 'prev-profile':
        this.prevProfile();
        break;
    }
  },

  /**
   * Like a profile
   * @param {string} userId - User ID to like
   */
  async likeProfile(userId) {
    try {
      const response = await Http.post('/api/matches/like', { user_id: userId });
      const result = await response.json();
      
      if (result.match) {
        Flash.success("It's a match! 🎉");
      } else {
        Flash.info('Profile liked!');
      }
      
      this.nextProfile();
    } catch (error) {
      Flash.error('Failed to like profile');
    }
  },

  /**
   * Pass on a profile
   * @param {string} userId - User ID to pass
   */
  async passProfile(userId) {
    try {
      await Http.post('/api/matches/pass', { user_id: userId });
      this.nextProfile();
    } catch (error) {
      Flash.error('Failed to pass profile');
    }
  },

  /**
   * Move to next profile
   */
  nextProfile() {
    this.profileIndex = (this.profileIndex + 1) % this.profiles.length;
    this.displayCurrentProfile();
  },

  /**
   * Move to previous profile
   */
  prevProfile() {
    this.profileIndex = this.profileIndex > 0 ? this.profileIndex - 1 : this.profiles.length - 1;
    this.displayCurrentProfile();
  },

  /**
   * Display current profile
   */
  displayCurrentProfile() {
    const profileContainer = document.querySelector('.profile-display');
    if (!profileContainer || !this.profiles[this.profileIndex]) return;

    const profile = this.profiles[this.profileIndex];
    this.currentProfile = profile;

    // Update profile display
    profileContainer.innerHTML = this.renderProfile(profile);
    
    // Add swipe handling
    const profileCard = profileContainer.querySelector('.profile-card');
    if (profileCard) {
      SwipeHandler.init(profileCard, {
        onSwipeLeft: () => this.passProfile(profile.id),
        onSwipeRight: () => this.likeProfile(profile.id),
        onDragMove: ({ deltaX }) => {
          // Visual feedback during drag
          profileCard.style.transform = `translateX(${deltaX * 0.1}px) rotate(${deltaX * 0.02}deg)`;
          profileCard.style.opacity = 1 - Math.abs(deltaX) * 0.001;
        },
        onDragEnd: () => {
          // Reset visual state
          profileCard.style.transform = '';
          profileCard.style.opacity = '';
        }
      });
    }
  },

  /**
   * Render profile HTML
   * @param {Object} profile - Profile data
   * @returns {string} Profile HTML
   */
  renderProfile(profile) {
    const interests = Array.isArray(profile.interests) ? profile.interests : [];
    const age = profile.age ? `, ${profile.age}` : '';
    
    return `
      <div class="profile-card" data-user-id="${profile.id}">
        <div class="profile-image">
          ${profile.profile_image ? 
            `<img src="${profile.profile_image}" alt="${profile.username}">` :
            `<div class="profile-placeholder">${profile.username.charAt(0).toUpperCase()}</div>`
          }
        </div>
        <div class="profile-info">
          <div class="profile-name">${Utils.sanitizeHTML(profile.username)}${age}</div>
          <div class="profile-details">${Utils.sanitizeHTML(profile.location || 'Location not specified')}</div>
          <div class="profile-bio">${Utils.sanitizeHTML(profile.bio || 'No bio available')}</div>
          ${interests.length > 0 ? `
            <div class="tags-container mt-sm">
              ${interests.map(interest => `<span class="tag tag-secondary">${Utils.sanitizeHTML(interest)}</span>`).join('')}
            </div>
          ` : ''}
        </div>
      </div>
    `;
  },

  /**
   * Setup session timeout warning
   */
  setupSessionTimeout() {
    const sessionDuration = 30 * 60 * 1000; // 30 minutes
    const warningTime = 5 * 60 * 1000; // 5 minutes before expiry

    setTimeout(() => {
      if (confirm('Your session will expire in 5 minutes. Do you want to extend it?')) {
        window.location.reload();
      }
    }, sessionDuration - warningTime);
  },

  /**
   * Logout user
   */
  async logout() {
    try {
      await Http.post('/logout.php');
      window.location.href = '/login.php';
    } catch (error) {
      Flash.error('Logout failed');
    }
  }
};

// Profile Management Module
const ProfileManager = {
  /**
   * Update user profile
   * @param {Object} profileData - Profile data to update
   */
  async updateProfile(profileData) {
    try {
      const response = await Http.post('/api/profile/update', profileData);
      const result = await response.json();
      
      Flash.success('Profile updated successfully!');
      return result;
    } catch (error) {
      Flash.error('Failed to update profile');
      throw error;
    }
  },

  /**
   * Upload profile image
   * @param {File} file - Image file to upload
   */
  async uploadImage(file) {
    try {
      const formData = new FormData();
      formData.append('image', file);
      
      const response = await Http.post('/api/profile/upload-image', formData);
      const result = await response.json();
      
      Flash.success('Image uploaded successfully!');
      return result;
    } catch (error) {
      Flash.error('Failed to upload image');
      throw error;
    }
  },

  /**
   * Add interest tag
   * @param {string} interest - Interest to add
   */
  addInterest(interest) {
    const container = document.querySelector('.interests-container');
    if (!container) return;

    const tag = document.createElement('span');
    tag.className = 'tag tag-interactive';
    tag.textContent = interest;
    tag.addEventListener('click', () => this.removeInterest(tag));
    
    container.appendChild(tag);
  },

  /**
   * Remove interest tag
   * @param {HTMLElement} tagElement - Tag element to remove
   */
  removeInterest(tagElement) {
    tagElement.remove();
  }
};

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  ThemeManager.init();
  DatingApp.init();
});

// Export modules for use in other files
window.DatingApp = DatingApp;
window.Utils = Utils;
window.Http = Http;
window.Flash = Flash;
window.FormHandler = FormHandler;
window.SwipeHandler = SwipeHandler;
window.ThemeManager = ThemeManager;
window.ProfileManager = ProfileManager;