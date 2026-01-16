// Authentication related JavaScript
class AuthManager {
    constructor() {
        this.setupValidation();
        this.setupRoleSelection();
        this.setupPasswordStrength();
    }

    setupValidation() {
        const forms = document.querySelectorAll('.auth-form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    setupRoleSelection() {
        const roleOptions = document.querySelectorAll('.role-option');
        roleOptions.forEach(option => {
            option.addEventListener('click', () => {
                // Remove selected class from all options
                roleOptions.forEach(opt => opt.classList.remove('selected'));
                // Add selected class to clicked option
                option.classList.add('selected');
                // Update hidden input
                const roleInput = document.querySelector('input[name="role"]');
                if (roleInput) {
                    roleInput.value = option.dataset.role;
                }
            });
        });
    }

    setupPasswordStrength() {
        const passwordInput = document.querySelector('input[name="password"]');
        const strengthBar = document.querySelector('.password-strength');

        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', (e) => {
                const strength = this.calculatePasswordStrength(e.target.value);
                strengthBar.className = 'password-strength';
                strengthBar.classList.add(`strength-${strength.level}`);
            });
        }
    }

    calculatePasswordStrength(password) {
        let score = 0;
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        const levels = ['weak', 'medium', 'medium', 'strong', 'strong'];
        return {
            level: levels[score] || 'weak',
            score: score
        };
    }

    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required]');

        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.showFieldError(input, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(input);
            }

            // Email validation
            if (input.type === 'email' && input.value) {
                if (!this.isValidEmail(input.value)) {
                    this.showFieldError(input, 'Please enter a valid email address');
                    isValid = false;
                }
            }

            // Phone validation (Nigerian)
            if (input.name === 'phone' && input.value) {
                if (!this.isValidNigerianPhone(input.value)) {
                    this.showFieldError(input, 'Please enter a valid Nigerian phone number');
                    isValid = false;
                }
            }
        });

        return isValid;
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    isValidNigerianPhone(phone) {
        return /^(\+234|0)[789][01]\d{8}$/.test(phone);
    }

    showFieldError(input, message) {
        this.clearFieldError(input);
        input.style.borderColor = '#d32f2f';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.cssText = 'color: #d32f2f; font-size: 0.875rem; margin-top: 0.25rem;';
        errorDiv.textContent = message;
        
        input.parentNode.appendChild(errorDiv);
    }

    clearFieldError(input) {
        input.style.borderColor = '';
        const existingError = input.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }
}

// Initialize auth manager
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.auth-form')) {
        new AuthManager();
    }
});