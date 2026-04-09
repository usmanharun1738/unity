My recommendation is to split it like this:

Platform core
Registration/login
Role-based access
Profile management
Basic course management
Announcements
Learning delivery
File uploads and syllabus pages
Module/week structure
Course homepage
Self-enrollment key and manual enrollment
Academic core
Assignments
Gradebook
Quizzes
Calendar
Enterprise integration
SSO/SAML support
If you want the platform to feel immediately more useful to students and instructors, I would build in this order:

Announcements
Module/week content structure
Assignments
Gradebook
Calendar
Quizzes
SSO/SAML
That order gives you visible classroom value faster and avoids starting with the heaviest integration first.

If you want, I can turn this into a proper roadmap with:

Phase 2 and Phase 3 milestones.
Feature-by-feature acceptance criteria.
A database/model plan for each module.

***********************
Plan: Post-MVP Academic Roadmap
Build in dependency order so we ship classroom value quickly without blocking on enterprise integrations: course experience -> content delivery -> communication -> assignments -> grading -> quizzes -> calendar -> SSO/SAML.

Steps

Phase 0 (Week 0): Scope lock and release criteria.
Define acceptance criteria per module and role (Student, Faculty/Instructor, Admin, DepartmentStaff), success metrics, and explicit exclusions.

Phase 1 (Weeks 1-2): Course Experience Foundation.
Implement student profile extension, course homepage, archive/restore lifecycle, and enrollment key workflows (manual + self-enrollment).
Depends on: Phase 0.
Parallel: none (foundation).

Phase 2 (Weeks 3-4): Content Delivery Core.
Implement module/week structure, syllabus editing, and secure file upload/download with enrollment-based access.
Depends on: Phase 1.
Parallel: can start UX polish in late Week 4.

Phase 3 (Week 5): Communication.
Implement course announcements (create/edit/pin), student announcement feed, optional read tracking.
Depends on: Phase 1.
Parallel: can run with late Phase 2 polish.

Phase 4 (Weeks 6-7): Assignments.
Implement assignment creation, due/open windows, student submission, late flag/policy handling, instructor review flow.
Depends on: Phase 2.

Phase 5 (Weeks 8-9): Grading.
Implement gradebook (points/percentage), instructor feedback/comments, CSV export.
Depends on: Phase 4.

Phase 6 (Weeks 10-11): Quizzes.
Implement MCQ auto-grading, short-answer manual grading, attempts, and time limits.
Depends on: Phase 5.
Phase 7 (Week 12): Calendar.
Implement personal deadlines calendar across enrolled courses (assignments + quizzes), upcoming/overdue indicators.
Depends on: Phases 4 and 6.

Phase 8 (Post Week 12 or Parallel Track): SSO/SAML.
Implement university IdP integration, account linking, and role provisioning.
Depends on: institutional coordination and security review.
Recommended: run as separate enterprise track so core rollout is not blocked.

Module Scope Mapping

User Management: role refinement + profile extension now; SSO/SAML in Phase 8.
Course Management: create/archive + enrollment keys + course homepage in Phase 1.
Content Delivery: files + syllabus + module/week in Phase 2.
Communication: announcements in Phase 3.
Assignments: due dates + submissions + late policy in Phase 4.
Grading: gradebook + feedback + CSV in Phase 5.
Quizzes: MCQ + short answer + attempts/time limits in Phase 6.
Calendar: cross-course deadline dashboard in Phase 7.
