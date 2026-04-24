<?php
include "db.php";


if (!isset($_SESSION["user_id"])) {
    $_SESSION["login_message"] = "Please login first.";
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];
$assignment_id = 0;

if (isset($_GET["id"])) {
    $assignment_id = (int) $_GET["id"];
}

$result = mysqli_query($conn, "SELECT assignments.*, users.name AS teacher_name
                              FROM assignments
                              JOIN users ON assignments.teacher_id = users.id
                              WHERE assignments.id = $assignment_id");

if (mysqli_num_rows($result) == 0) {
    echo "Assignment not found.";
    exit;
}

$assignment = mysqli_fetch_assoc($result);
$student_task = null;
$student_submissions = null;

if ($role == "teacher" && (int) $assignment["teacher_id"] !== $user_id) {
    echo "You are not allowed to view this assignment.";
    exit;
}

if ($role == "student") {
    $student_task_result = mysqli_query($conn, "SELECT status, submission_pdf, submitted_at
                                               FROM student_tasks
                                               WHERE student_id = $user_id AND assignment_id = $assignment_id");

    if ($student_task_result && mysqli_num_rows($student_task_result) > 0) {
        $student_task = mysqli_fetch_assoc($student_task_result);
    }
}

if ($role == "teacher") {
    $student_submissions = mysqli_query($conn, "SELECT users.name,
                                               users.email,
                                               COALESCE(student_tasks.status, 'pending') AS status,
                                               student_tasks.submission_pdf,
                                               student_tasks.submitted_at
                                               FROM users
                                               LEFT JOIN student_tasks ON student_tasks.student_id = users.id
                                                                      AND student_tasks.assignment_id = $assignment_id
                                               WHERE users.role = 'student' AND users.is_approved = 1
                                               ORDER BY users.name ASC");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assignment Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h2>Assignment Details</h2>
        <div>
            <?php if ($_SESSION["role"] == "teacher") { ?>
                <a href="teacher.php">Back</a>
            <?php } else { ?>
                <a href="student.php">Back</a>
            <?php } ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="items">
        <div class="box bigbox">
            <h1><?php echo htmlspecialchars($assignment["title"]); ?></h1>
            <p><b>Subject:</b> <?php echo htmlspecialchars($assignment["subject"]); ?></p>
            <p><b>Teacher:</b> <?php echo htmlspecialchars($assignment["teacher_name"]); ?></p>
            <p><b>Deadline:</b> <?php echo date("d M Y h:i A", strtotime($assignment["deadline"])); ?></p>

            <?php if ($assignment["pdf_file"] != "") { ?>
                <p><b>PDF:</b> <a href="<?php echo htmlspecialchars($assignment["pdf_file"]); ?>" target="_blank">Open Assignment PDF</a></p>
            <?php } ?>

            <h3>Description</h3>
            <p><?php echo nl2br(htmlspecialchars($assignment["description"])); ?></p>

            <?php if ($role == "student") { ?>
                <?php
                $current_status = $student_task["status"] ?? "pending";
                $current_pdf = $student_task["submission_pdf"] ?? "";
                $submitted_at = $student_task["submitted_at"] ?? "";
                ?>
                <div class="submission-panel">
                    <h3>Your Submission</h3>
                    <p><b>Status:</b> <?php echo htmlspecialchars(ucfirst($current_status)); ?></p>
                    <?php if ($current_pdf != "") { ?>
                        <p><b>Submitted PDF:</b> <a href="<?php echo htmlspecialchars($current_pdf); ?>" target="_blank">Open Your Submitted PDF</a></p>
                        <p><b>Submitted On:</b> <?php echo date("d M Y h:i A", strtotime($submitted_at)); ?></p>
                    <?php } else { ?>
                        <p class="muted-text">You have not uploaded a completed PDF yet.</p>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php if ($role == "teacher") { ?>
            <div class="box bigbox">
                <h3>Student Submissions</h3>

                <?php if (!$student_submissions || mysqli_num_rows($student_submissions) == 0) { ?>
                    <p>No students available yet.</p>
                <?php } ?>

                <?php while ($student = mysqli_fetch_assoc($student_submissions)) { ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($student["name"]); ?></h3>
                        <p><b>Email:</b> <?php echo htmlspecialchars($student["email"]); ?></p>
                        <p><b>Status:</b> <?php echo htmlspecialchars(ucfirst($student["status"])); ?></p>

                        <?php if (($student["submission_pdf"] ?? "") != "") { ?>
                            <p><b>Submitted PDF:</b> <a href="<?php echo htmlspecialchars($student["submission_pdf"]); ?>" target="_blank">Open Student PDF</a></p>
                            <p><b>Submitted On:</b> <?php echo date("d M Y h:i A", strtotime($student["submitted_at"])); ?></p>
                        <?php } else { ?>
                            <p class="muted-text">This student has not uploaded a completed PDF yet.</p>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</body>
</html>
