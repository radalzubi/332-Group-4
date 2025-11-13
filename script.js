// script.js

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("login-form");
    const username = document.getElementById("username");
    const password = document.getElementById("password");
    const clientError = document.getElementById("clientError");
    const card = document.getElementById("login-card");

    if (!form) return;

    form.addEventListener("submit", (e) => {
        const u = username.value.trim();
        const p = password.value.trim();

        clientError.textContent = "";
        clientError.classList.add("hidden");

        // Remove previous shake animation (so it can replay)
        [username, password, card].forEach(el => {
            el.classList.remove("shake");
            void el.offsetWidth; // reset animation
        });

        if (u === "" || p === "") {
            e.preventDefault();

            clientError.textContent = "Both username and password are required.";
            clientError.classList.remove("hidden");

            // Shake all three: card, username, password
            username.classList.add("shake");
            password.classList.add("shake");
            card.classList.add("shake");

            return;
        }
    });
});
