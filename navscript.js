<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sections = document.querySelectorAll("section");
        const navLinks = document.querySelectorAll("nav a");

        const observerOptions = {
            root: null, // Viewport
            threshold: 0.6, // Trigger when 60% of the section is in view
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const activeSectionId = entry.target.id;
                    navLinks.forEach((link) => {
                        if (link.getAttribute("href").substring(1) === activeSectionId) {
                            link.classList.add("active");
                        } else {
                            link.classList.remove("active");
                        }
                    });
                }
            });
        }, observerOptions);

        sections.forEach((section) => {
            observer.observe(section);
        });
    });
</script>
