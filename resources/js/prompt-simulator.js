// Simulador para probar respuestas del prompt antes de guardarlo
document.addEventListener('DOMContentLoaded', function () {
    const configEl = document.getElementById('blueprint-config');
    const csrfToken = configEl?.getAttribute('data-csrf-token') ||
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const testForm = document.getElementById('testPromptForm');
    const testResult = document.getElementById('testPromptResult');
    const testOutput = document.getElementById('testPromptOutput');
    const btnSubmit = document.getElementById('btnSubmitTestPrompt');

    if (testForm && testResult && testOutput && btnSubmit) {
        testForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const message = document.getElementById('test_message')?.value || '';
            const prompt = document.getElementById('live_system_prompt')?.value ||
                document.getElementById('test_system_prompt')?.value || '';
            const actionUrl = this.getAttribute('action');

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Consultando...';
            testResult.classList.remove('d-none');

            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    mensaje: message,
                    prompt_personalizado: prompt
                })
            })
                .then(res => res.json())
                .then(data => {
                    testOutput.textContent = data.respuesta || 'Sin respuesta';
                    testResult.classList.remove('d-none');
                })
                .catch(err => {
                    testOutput.textContent = 'Error al procesar la solicitud.';
                    testResult.classList.remove('d-none');
                })
                .finally(() => {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fas fa-paper-plane mr-1"></i> Probar IA';
                });
        });
    }
});
