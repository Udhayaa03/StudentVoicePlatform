// Login page user type switching
document.addEventListener('DOMContentLoaded', function() {
    const userTypeButtons = document.querySelectorAll('.user-type-selector button');
    const userForms = document.querySelectorAll('.user-form');
    
    userTypeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active button
            userTypeButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Show corresponding form
            const userType = this.getAttribute('data-user-type');
            userForms.forEach(form => {
                form.classList.remove('active');
                if (form.id === `${userType}-form`) {
                    form.classList.add('active');
                }
            });
        });
    });
});