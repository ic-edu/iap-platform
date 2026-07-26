# Event-Driven Architecture & Activity Logging — IAP

IAP menggunakan Event & Listener terpisah untuk merekam seluruh audit log secara otomatis tanpa mengotori logika controller.

---

## Registered Events & Listeners

| Event | Listener | Action Recorded |
|---|---|---|
| `UserLoggedIn` | `LogUserLogin` | `auth.login` |
| `UserLoggedOut` | `LogUserLogout` | `auth.logout` |
| `RoleAssigned` | `LogRoleAssigned` | `role.assigned` |
| `CourseCreated` | `LogCourseCreated` | `academic.course_created` |
| `QuestionCreated` | `LogQuestionActivity` | `question_bank.question_created` |
| `QuestionUpdated` | `LogQuestionActivity` | `question_bank.question_updated` |
| `QuestionDeleted` | `LogQuestionActivity` | `question_bank.question_deleted` |
| `TestCreated` | `LogTestActivity` | `assessment.test_created` |
| `TestPublished` | `LogTestActivity` | `assessment.test_published` |
| `AttemptStarted` | `LogAssessmentDeliveryActivity` | `cbt.attempt_started` |
| `AttemptResumed` | `LogAssessmentDeliveryActivity` | `cbt.attempt_resumed` |
| `AttemptSubmitted` | `LogAssessmentDeliveryActivity` | `cbt.attempt_submitted` |
| `AttemptExpired` | `LogAssessmentDeliveryActivity` | `cbt.attempt_expired` |
| `RuleViolationDetected` | `LogAssessmentDeliveryActivity` | `cbt.violation_detected` |
