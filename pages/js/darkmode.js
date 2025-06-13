const body = document.body;

// Cek status saat halaman dimuat
window.addEventListener("DOMContentLoaded", () => {
    if (localStorage.getItem("darkmode") === "true") {
        body.classList.add("dark");
    }
});

// Fungsi toggle
function darkmode() {
    body.classList.toggle("dark");
    const isDark = body.classList.contains("dark");
    localStorage.setItem("darkmode", isDark);
}
