
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            sidebar.style.display = sidebar.style.display === "none" ? "block" : "none";
        }

        function toggleDropdown(id, element) {
            const dropdown = document.getElementById(id);
            dropdown.classList.toggle("show");

            // Toggle icon rotation
            const icon = element.querySelector("i");
            icon.classList.toggle("rotate");
        }