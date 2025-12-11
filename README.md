## EduQuest - Teaching and Learning Management (Mini LMS)

This is a small educational prototype of an LMS module for the **Software Engineering** subject,
focused on **Managing Assignments and Notes**.

Technologies used: **basic HTML, CSS, PHP, and JavaScript** (for simple interactivity in the browser).

### Features

- **Assignment Management**
  - Create assignments with title, description, deadline, maximum score, and rubric.
  - List and track assignments, see how many submissions each has.
  - Students can upload assignment submissions (file upload).
  - Instructors can grade submissions and leave feedback.
  - Deadlines are visually highlighted (overdue / due soon / on track).

- **Notes Management**
  - Create notes with title, topic, and summary.
  - Optional file attachment for each note (PDF, slides, etc.).
  - Simple **version control**: saving a note with an existing title creates a new version.
  - View all versions of a note to see how content evolved.

- **File Handling**
  - Uploaded assignment and note files are stored in an `uploads` folder.
  - Files can be downloaded using the `download.php` script.

- **Assessment & Gradebook**
  - Gradebook summary based on graded submissions.
  - Shows per-student total score and percentage across assignments.
  - Basic course statistics: average, highest, and lowest percentage.

- **Mini System Integration**
  - Shared navigation and consistent layout across pages.
  - Simple "role" toggle between **instructor** and **student** on relevant pages
    (simulates integration with user management).

### Main Pages

- `index.php` — Dashboard / overview of the EduQuest LMS module.
- `assignments.php` — Assignment creation, listing, submission, and grading.
- `notes.php` — Notes creation, organization, and version control with attachments.
- `gradebook.php` — Grade summary and course statistics.

All data is stored in simple JSON files in the `data` folder using `data_store.php`.

### How to Run (Locally)

1. Make sure you have **PHP** installed.
2. Place all project files in one folder (for example: `EduQuestLMS`).
3. From a terminal/PowerShell in that folder, run:

   ```bash
   php -S localhost:8000
   ```

4. Open your browser and go to:

   `http://localhost:8000/index.php`

5. Navigate using the top menu to explore Assignments, Notes, and Gradebook.

> Note: This is a simple prototype for learning purposes, not a production-ready LMS.


