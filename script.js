function showForm(formType) {
    // Hide all forms initially
    const forms = document.querySelectorAll('.form');
    forms.forEach(form => {
        form.classList.add('hidden');
    });

    // Show the selected form
    const selectedForm = document.getElementById(formType + '-form');
    if (selectedForm) {
        selectedForm.classList.remove('hidden');
    }
}
