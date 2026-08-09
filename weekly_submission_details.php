<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/weekly_helpers.php';

requireAdministrativeUser();


/* =========================================================
   GET SUBMISSION
   ========================================================= */

$submissionId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (
    $submissionId === false ||
    $submissionId === null ||
    $submissionId < 1
) {

    redirectToDashboard();
}


$submission =
    loadSubmission(
        $conn,
        (int) $submissionId
    );


if (
    $submission === null
) {

    http_response_code(
        404
    );

    exit(
        'Weekly submission not found.'
    );
}


/* =========================================================
   ACCESS CONTROL
   ========================================================= */

$role =
    currentRole();


$userId =
    currentUserId();


if (
    !reviewerCanAccessSubmission(

        $submission,

        $userId,

        $role

    )
) {

    http_response_code(
        403
    );

    exit(
        'This weekly submission is not assigned to your account.'
    );
}


preventSelfApproval(
    (int)
    $submission[
        'field_officer_id'
    ]
);


/* =========================================================
   ATTENDANCE RECORDS
   NO PHOTO FIELD
   ========================================================= */

$attendanceStmt =
    $conn->prepare(
        "SELECT

            ae.id,

            ae.action_type,

            ae.latitude,

            ae.longitude,

            ae.is_locked,

            ae.created_at

         FROM weekly_submission_records wsr

         INNER JOIN attendance_events ae
            ON ae.id =
               wsr.attendance_event_id

         WHERE
            wsr.submission_id = ?

         ORDER BY
            ae.created_at ASC,
            ae.id ASC"
    );


$attendanceStmt->bind_param(
    'i',
    $submissionId
);


$attendanceStmt->execute();


$attendanceResult =
    $attendanceStmt
        ->get_result();


$attendanceRecords = [];


while (
    $row =
    $attendanceResult->fetch_assoc()
) {

    $attendanceRecords[] =
        $row;

}


$attendanceStmt->close();


/* =========================================================
   APPROVAL HISTORY
   ========================================================= */

$historyStmt =
    $conn->prepare(
        "SELECT

            ah.*,

            u.name AS reviewer_name

         FROM approval_history ah

         LEFT JOIN users u
            ON u.id =
               ah.reviewer_id

         WHERE
            ah.submission_id = ?

         ORDER BY
            ah.created_at ASC,
            ah.id ASC"
    );


$historyStmt->bind_param(
    'i',
    $submissionId
);


$historyStmt->execute();


$historyResult =
    $historyStmt
        ->get_result();


$history = [];


while (
    $row =
    $historyResult->fetch_assoc()
) {

    $history[] =
        $row;

}


$historyStmt->close();


/* =========================================================
   REVIEW PERMISSIONS
   ========================================================= */

$status =
    (string)
    $submission[
        'status'
    ];


$canAdminOfficerReview = (

    $role ===
        'admin_officer'

    &&

    (int)
    $submission[
        'admin_officer_id'
    ]
    ===
    $userId

    &&

    in_array(

        $status,

        [
            'submitted',
            'resubmitted'
        ],

        true

    )

);


$canManagerReview = (

    $role ===
        'admin_manager'

    &&

    (int)
    $submission[
        'admin_manager_id'
    ]
    ===
    $userId

    &&

    in_array(

        $status,

        [
            'pending_manager_review',
            'admin_officer_approved'
        ],

        true

    )

);


$canReview =
    $canAdminOfficerReview ||
    $canManagerReview;


/* =========================================================
   BACK PAGE
   ========================================================= */

$backPage =
    match ($role) {

        'admin_officer' =>
            'admin_officer_panel.php',

        'admin_manager' =>
            'admin_manager_panel.php',

        default =>
            'admin_panel.php'

    };


$message =
    trim(
        (string)
        ($_GET['msg'] ?? '')
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>

Weekly Submission Details

</title>


<link
rel="stylesheet"
href="<?= h(
    appUrl(
        'review_panel.css'
    )
) ?>"
>


</head>


<body>


<header class="topbar">


<div>

<h1>
    FieldTrack
</h1>

<p>

Weekly Submission
#<?= (int)
    $submissionId ?>

</p>

</div>


<div class="topbar-links">


<a
href="<?= h(
    appUrl(
        $backPage
    )
) ?>"
>

Back to Dashboard

</a>


<a
class="logout"
href="<?= h(
    appUrl(
        'logout.php'
    )
) ?>"
>

Logout

</a>


</div>


</header>


<main class="container">


<?php if (
    $message !== ''
): ?>


<div class="message">

<?= h(
    $message
) ?>

</div>


<?php endif; ?>


<!-- =====================================================
     SUBMISSION INFORMATION
     ===================================================== -->

<section class="details-card">


<h2>

Submission Information

</h2>


<div class="detail-grid">


<div class="detail-item">

<span>
Field Officer
</span>

<strong>

<?= h(
    $submission[
        'field_officer_name'
    ]
) ?>

(@<?= h(
    $submission[
        'field_officer_username'
    ]
) ?>)

</strong>

</div>


<div class="detail-item">

<span>
Week
</span>

<strong>

<?= h(
    formatDateValue(
        (string)
        $submission[
            'week_start'
        ]
    )
) ?>

—

<?= h(
    formatDateValue(
        (string)
        $submission[
            'week_end'
        ]
    )
) ?>

</strong>

</div>


<div class="detail-item">

<span>
Status
</span>

<strong>

<?= h(
    getWeekStatusLabel(
        $status
    )
) ?>

</strong>

</div>


<div class="detail-item">

<span>
Admin Officer
</span>

<strong>

<?= h(
    $submission[
        'admin_officer_name'
    ]
) ?>

</strong>

</div>


<div class="detail-item">

<span>
Admin Manager
</span>

<strong>

<?= h(
    $submission[
        'admin_manager_name'
    ]
) ?>

</strong>

</div>


<div class="detail-item">

<span>
Submitted At
</span>

<strong>

<?= h(
    formatDateTimeValue(
        $submission[
            'submitted_at'
        ]
    )
) ?>

</strong>

</div>


</div>


<?php if (
    !empty(
        $submission[
            'latest_rejection_reason'
        ]
    )
): ?>


<div class="reason-box">

<strong>

Latest rejection reason:

</strong>

<br>


<?= nl2br(
    h(
        $submission[
            'latest_rejection_reason'
        ]
    )
) ?>


</div>


<?php endif; ?>


</section>


<!-- =====================================================
     ATTENDANCE
     ===================================================== -->

<section class="details-card">


<h2>

Attendance Records

</h2>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>
#
</th>

<th>
Action
</th>

<th>
Date / Time
</th>

<th>
Latitude
</th>

<th>
Longitude
</th>

<th>
Locked
</th>

</tr>

</thead>


<tbody>


<?php if (
    count(
        $attendanceRecords
    ) === 0
): ?>


<tr>

<td colspan="6">

No attendance records are linked to this submission.

</td>

</tr>


<?php endif; ?>



<?php foreach (
    $attendanceRecords
    as
    $record
): ?>


<tr>


<td>

<?= (int)
    $record[
        'id'
    ] ?>

</td>


<td>

<strong>

<?= h(
    $record[
        'action_type'
    ]
) ?>

</strong>

</td>


<td>

<?= h(
    formatDateTimeValue(
        $record[
            'created_at'
        ]
    )
) ?>

</td>


<td>

<?= h(
    $record[
        'latitude'
    ]
) ?>

</td>


<td>

<?= h(
    $record[
        'longitude'
    ]
) ?>

</td>


<td>

<?=

(int)
$record[
    'is_locked'
]
=== 1

? 'Yes'

: 'No'

?>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</section>


<!-- =====================================================
     HISTORY
     ===================================================== -->

<section class="details-card">


<h2>

Approval History

</h2>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>
Date / Time
</th>

<th>
Reviewer
</th>

<th>
Role
</th>

<th>
Decision
</th>

<th>
Status Change
</th>

<th>
Reason
</th>

<th>
Comment
</th>

</tr>

</thead>


<tbody>


<?php if (
    count($history) === 0
): ?>


<tr>

<td colspan="7">

No approval history yet.

</td>

</tr>


<?php endif; ?>



<?php foreach (
    $history as $item
): ?>


<tr>


<td>

<?= h(
    formatDateTimeValue(
        $item[
            'created_at'
        ]
    )
) ?>

</td>


<td>

<?= h(
    $item[
        'reviewer_name'
    ]
    ?? 'System'
) ?>

</td>


<td>

<?= h(
    $item[
        'reviewer_role'
    ]
) ?>

</td>


<td>

<?= h(
    ucfirst(
        (string)
        $item[
            'decision'
        ]
    )
) ?>

</td>


<td>

<?= h(
    $item[
        'previous_status'
    ]
    ?? '—'
) ?>

→

<?= h(
    $item[
        'new_status'
    ]
) ?>

</td>


<td>

<?= nl2br(
    h(
        $item[
            'reason'
        ]
        ?? '—'
    )
) ?>

</td>


<td>

<?= nl2br(
    h(
        $item[
            'comment'
        ]
        ?? '—'
    )
) ?>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</section>


<!-- =====================================================
     REVIEW
     ===================================================== -->

<?php if (
    $canReview
): ?>


<section class="details-card">


<h2>

Review Submission

</h2>


<div class="review-grid">


<form

class="review-form"

action="<?= h(
    appUrl(
        'process_weekly_review.php'
    )
) ?>"

method="POST"

onsubmit="
return confirm(
    'Approve this weekly submission?'
);
"

>


<h3>

Approve Submission

</h3>


<input

type="hidden"

name="submission_id"

value="<?= (int)
    $submissionId ?>"

>


<input

type="hidden"

name="review_action"

value="<?=

$canAdminOfficerReview

? 'approve_level1'

: 'approve_final'

?>"

>


<label for="approval_comment">

Comment

</label>


<textarea

id="approval_comment"

name="comment"

maxlength="1000"

placeholder="Optional approval comment"

></textarea>


<button

class="approve-button"

type="submit"

>


<?=

$canAdminOfficerReview

? 'Admin Officer Approve'

: 'Final Approve'

?>


</button>


</form>



<form

class="review-form"

action="<?= h(
    appUrl(
        'process_weekly_review.php'
    )
) ?>"

method="POST"

onsubmit="
return confirm(
    'Reject this weekly submission and return it to the Field Officer?'
);
"

>


<h3>

Reject Submission

</h3>


<input

type="hidden"

name="submission_id"

value="<?= (int)
    $submissionId ?>"

>


<input

type="hidden"

name="review_action"

value="<?=

$canAdminOfficerReview

? 'reject_level1'

: 'reject_final'

?>"

>


<label for="rejection_reason">

Rejection Reason

</label>


<textarea

id="rejection_reason"

name="reason"

maxlength="2000"

required

placeholder="Explain why this submission is being rejected"

></textarea>


<button

class="reject-button"

type="submit"

>


<?=

$canAdminOfficerReview

? 'Admin Officer Reject'

: 'Final Reject'

?>


</button>


</form>


</div>


</section>


<?php else: ?>


<div class="message warning-message">

This submission is not currently waiting for review by your role.

</div>


<?php endif; ?>


</main>


</body>

</html>