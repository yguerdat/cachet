@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const globalBox = document.querySelector('input[name="global"]');
            const componentBoxes = document.querySelectorAll('input[name="components[]"]');
            if (!globalBox || componentBoxes.length === 0) return;

            const setComponentsDisabled = (disabled) => {
                componentBoxes.forEach((box) => {
                    box.disabled = disabled;
                    if (disabled) box.checked = false;
                    box.closest('label')?.classList.toggle('opacity-50', disabled);
                });
            };

            setComponentsDisabled(globalBox.checked);

            globalBox.addEventListener('change', () => {
                setComponentsDisabled(globalBox.checked);
            });

            componentBoxes.forEach((box) => {
                box.addEventListener('change', () => {
                    if (box.checked && globalBox.checked) {
                        globalBox.checked = false;
                    }
                });
            });
        });
    </script>
@endonce
