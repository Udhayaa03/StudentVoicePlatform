// Client-side validation for login forms
document.addEventListener('DOMContentLoaded', function() {
    const loginForms = document.querySelectorAll('.user-form');
    
    loginForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate all required fields
            const inputs = this.querySelectorAll('input[required], select[required]');
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = 'red';
                    
                    // Reset border color when user starts typing
                    input.addEventListener('input', function() {
                        this.style.borderColor = '';
                    });
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    });
});