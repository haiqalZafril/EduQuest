// EduQuest LMS - Basic front-end helpers (JavaScript)
// Note: The assignment likely says "Java"; here we demonstrate JavaScript for client-side behaviour.

document.addEventListener("DOMContentLoaded", () => {
    highlightDeadlines();
});

function highlightDeadlines() {
    const rows = document.querySelectorAll("[data-deadline]");
    const now = new Date();

    rows.forEach(row => {
        const deadlineStr = row.getAttribute("data-deadline");
        if (!deadlineStr) return;

        const deadline = new Date(deadlineStr);
        const badge = row.querySelector(".badge-deadline");
        if (!badge) return;

        if (now > deadline) {
            badge.textContent = "Overdue";
            badge.classList.add("overdue", "badge");
        } else {
            const diffMs = deadline.getTime() - now.getTime();
            const diffHours = diffMs / (1000 * 60 * 60);
            if (diffHours <= 48) {
                badge.textContent = "Due Soon";
                badge.classList.add("due-soon", "badge");
            } else {
                badge.textContent = "On Track";
                badge.classList.add("completed", "badge");
            }
        }
    });
}


