# ACL — Anyone Can Learn
# Master System Specification

## 1. Project Identity

Name:
Anyone Can Learn (ACL)

Type:
Open-source educational ecosystem and learning platform.

Primary Market:
Nigerian universities and tertiary education.

Long-Term Market:
Africa and international education.

Primary Goal:
Build a unified educational ecosystem connecting university learning,
professional skills, practical training, tutors, learners, institutions,
communities, assessments, certifications, and AI-assisted education.

---

# 2. Core Vision

ACL is intended to become a comprehensive educational platform that
supports academic education, professional development, practical learning,
community collaboration, tutoring, assessments, certification and
AI-assisted learning.

The system must be designed for long-term enterprise-scale expansion.

ACL must not be architected as a simple university LMS.

It must be capable of supporting:

- Multiple universities
- Multiple faculties
- Multiple departments
- Multiple programmes
- Multiple academic sessions
- Multiple semesters
- Multiple course structures
- External learners
- Tutors
- Lecturers
- Administrators
- Institutions
- Organizations
- Professional learning
- Practical laboratories
- AI-assisted learning
- Assessments
- Certifications
- Payments
- Community features
- Analytics
- Future mobile applications
- Future public APIs

---

# 3. Architectural Principle

ACL will initially use a modular monolith architecture.

Technology foundation:

Backend:
Laravel 13 / PHP 8.4+

Database:
SQLite for initial development where appropriate.

Production database:
PostgreSQL or another enterprise relational database selected during
production architecture.

Frontend:
Laravel Blade + modern JavaScript architecture initially.

Build system:
Vite.

Version control:
Git + GitHub.

Development:
GitHub Codespaces.

Architecture must allow future extraction of selected modules into
independent services without requiring a complete rewrite.

---

# 4. Core Design Principles

1. Security by default.
2. Modular architecture.
3. Strong domain boundaries.
4. Explicit authorization.
5. Database integrity.
6. Auditability.
7. API readiness.
8. Testability.
9. Accessibility.
10. Mobile responsiveness.
11. Internationalization readiness.
12. Offline/PWA readiness.
13. AI must remain optional and replaceable.
14. Payment providers must remain replaceable.
15. Storage providers must remain replaceable.
16. External integrations must be isolated.
17. No vendor lock-in where reasonably avoidable.
18. Avoid premature microservices.
19. Avoid unnecessary complexity.
20. Every major architectural decision must be documented.

---

# 5. User Categories

ACL must support distinct capabilities for:

- Platform administrators
- Institution administrators
- Faculty administrators
- Department administrators
- Academic coordinators
- Lecturers
- Tutors
- University students
- External learners
- Professional learners
- Content creators
- Assessment administrators
- Support personnel
- Future organizational users

The authorization system must NOT rely solely on a single `role`
column in the users table.

Permissions must be capability-based and extensible.

---

# 6. Academic Hierarchy

The platform must support a hierarchy similar to:

Institution
    ↓
Faculty / School / College
    ↓
Department
    ↓
Programme
    ↓
Academic Session
    ↓
Semester
    ↓
Level
    ↓
Course
    ↓
Course Offering
    ↓
Learning Content

The architecture must support differences between institutions.

Do not assume every university uses the same terminology or structure.

---

# 7. Learning Model

ACL must distinguish between:

- Course
- Course offering
- Learning path
- Module
- Chapter
- Lesson
- Topic
- Resource
- Practical activity
- Assignment
- Assessment
- Question
- Submission
- Grade
- Certificate

Academic course structures must remain separate from generic professional
learning paths where appropriate.

---

# 8. Content

Content may include:

- Text
- Images
- PDFs
- Documents
- Embedded videos
- External resources
- Presentations
- Code examples
- Interactive exercises
- Practical laboratories
- Quizzes
- Assignments
- AI-assisted content

Heavy video hosting should not be required for the initial platform.

---

# 9. AI Architecture

AI must be implemented behind an abstraction layer.

ACL must NOT directly couple the entire application to one AI provider.

The system should support future providers including:

- Cloud APIs
- Open-source models
- Locally hosted models
- Institution-hosted models

AI functionality may include:

- Content generation
- Content assistance
- Question generation
- Explanation
- Summarization
- Personalized learning
- Assessment assistance
- Tutor assistance
- Search
- Recommendation
- Analytics assistance

AI output must not automatically become authoritative educational content
without the appropriate human approval workflow.

---

# 10. Financial Model

ACL may support:

- Free university course access
- Paid course unlocking
- External learner subscriptions
- Paid tutorials
- Tutor monetization
- Professional courses
- Certifications
- Institutional subscriptions
- Corporate training
- Sponsored content
- Future AI premium functionality

Payment providers must be abstracted behind a payment service layer.

---

# 11. Security Requirements

Security is a first-class architectural requirement.

The system must provide:

- Authentication
- Authorization
- Role/permission management
- Input validation
- CSRF protection
- XSS protection
- SQL injection protection
- Rate limiting
- Secure sessions
- Password security
- MFA readiness
- Audit logging
- Security event logging
- File upload security
- API authentication
- Secrets management
- Secure payment integration
- Data access controls
- Tenant/institution isolation where applicable

---

# 12. Development Rule

AI coding agents must NOT make large architectural changes without
first examining:

1. ACL Master Specification
2. ACL engineering constitution
3. Existing architecture
4. Existing database schema
5. Existing modules
6. Existing tests
7. Relevant ADRs

Before implementing a major feature, the agent must explain:

- What it intends to change
- Which files will change
- Which database changes are required
- Which security implications exist
- Which tests will be created

---

# 13. Implementation Strategy

ACL must be developed incrementally.

Phase 1:

- Application foundation
- Authentication
- Identity
- Authorization
- Institution structure
- Academic structure
- Course management
- Learning content
- Basic student dashboard
- Basic lecturer/tutor dashboard
- Assessments
- Audit logging

Phase 2:

- Community
- Messaging
- Notifications
- Gamification
- Analytics
- Reports
- Tutor marketplace

Phase 3:

- AI
- Adaptive learning
- OCR
- Advanced grading
- Recommendation systems
- PWA/offline functionality
- Public API

Phase 4:

- Institutional integrations
- Advanced enterprise features
- Mobile applications
- External partnerships
- Advanced infrastructure

---

# 14. Non-Goals During Foundation

Do NOT initially implement:

- Microservices
- Kubernetes
- Complex event-driven infrastructure
- Distributed databases
- AI model hosting
- Complex payment integrations
- Native mobile applications
- Massive video infrastructure

These may be introduced when justified by actual requirements.

---

# 15. Quality Standard

Every production feature must have:

- Appropriate tests
- Authorization checks
- Validation
- Error handling
- Logging where appropriate
- Documentation
- Migration strategy
- Rollback consideration
- Security consideration

No feature is considered complete merely because its UI works.
