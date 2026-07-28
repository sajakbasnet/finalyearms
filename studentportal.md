# Final Year Project Management Portal (FYP Portal)

Version: 1.0

---

# Overview

The Final Year Project Management Portal is a web application that helps universities manage the complete lifecycle of final year projects.

Instead of using emails and paper submissions, students and supervisors communicate through a centralized platform.

The system tracks every proposal, revision, approval, milestone, and final submission.

---

# Goals

- Digitize final year project management.
- Reduce paperwork.
- Track proposal approval process.
- Maintain communication history.
- Track project progress.
- Allow administrators to monitor the entire department.
- Maintain complete submission history.

---

# User Roles

## Admin

Responsible for managing the entire system.

Permissions

- Manage students
- Manage teachers
- Manage departments
- Manage academic sessions
- Manage batches
- Assign supervisors
- Configure deadlines
- Configure project statuses
- View reports
- View analytics
- Manage announcements

---

## Teacher (Supervisor)

Responsible for supervising assigned students.

Permissions

- View assigned students
- Review proposals
- Approve proposal
- Reject proposal
- Request revisions
- Add comments
- Reply to students
- Review progress reports
- Approve milestones
- Evaluate final projects
- Download uploaded files

---

## Student

Responsible for project submission.

Permissions

- View assigned supervisor
- Submit proposal
- Edit proposal before approval
- Upload revisions
- Reply to teacher comments
- Submit progress reports
- Upload final report
- Upload presentation
- View project timeline
- Receive notifications

---

# Authentication

Features

- Login
- Logout
- Forgot password
- Change password
- Email verification
- Role-based authorization

Roles

- Admin
- Teacher
- Student

---

# Departments

Admin can

- Create department
- Edit department
- Delete department

Department Fields

- Name
- Code
- Description

---

# Academic Session

Examples

2026-2027

2027-2028

Fields

- Name
- Start Date
- End Date
- Active Status

---

# Teacher Management

Fields

- Name
- Email
- Employee ID
- Phone
- Department
- Designation
- Maximum Students

Features

- CRUD
- Assign students
- View workload

---

# Student Management

Fields

- Name
- Registration Number
- Roll Number
- Email
- Phone
- Department
- Batch
- Session

Features

- CRUD
- Bulk Import CSV
- Assign Supervisor

---

# Student Groups

Support

- Individual Project
- Group Project

Fields

- Group Name
- Members
- Supervisor

---

# Project

Each project belongs to

- Student or Group
- Supervisor
- Academic Session

Fields

- Title
- Description
- Domain
- Technology Stack
- Category
- Current Status

---

# Proposal Submission

Student submits

- Project Title
- Abstract
- Background
- Problem Statement
- Objectives
- Scope
- Methodology
- Literature Review
- Timeline
- Expected Outcome
- Technologies
- References
- Proposal PDF

Proposal can be edited until submitted.

---

# Proposal Status

Possible Status

Draft

Submitted

Under Review

Revision Requested

Resubmitted

Approved

Rejected

Cancelled

---

# Proposal Review

Teacher can

Approve

Reject

Request Revision

Comment on proposal

Teacher can comment on

- Entire proposal
- Individual sections

Every review should be saved.

---

# Proposal Conversation

Teacher

↓

Comment

↓

Student Reply

↓

Teacher Reply

↓

Student Reply

Complete history must remain.

Support attachments.

---

# Proposal Versioning

Every new upload becomes a new version.

Example

Proposal v1

Proposal v2

Proposal v3

Teacher can download previous versions.

---

# Progress Reports

Student uploads

Progress Title

Description

Percentage Completed

Files

Demo Link

Git Repository

Teacher reviews

Approve

Reject

Comment

---

# Milestones

Examples

Proposal Submitted

Proposal Approved

Requirement Analysis

Design

Development

Testing

Documentation

Final Report

Presentation

Completed

Each milestone contains

- Due Date
- Status
- Remarks

---

# Final Submission

Student uploads

Final Thesis PDF

Presentation Slides

Poster

Source Code ZIP

Documentation

Demo Video

GitHub Repository

---

# Evaluation

Teacher evaluates

Innovation

Implementation

Documentation

Presentation

Testing

Overall Score

Remarks

---

# Notifications

Student receives

Proposal approved

Proposal rejected

Teacher commented

Revision requested

Deadline reminder

Teacher receives

Proposal submitted

Progress submitted

Student replied

Admin receives

Pending approvals

Late submissions

---

# Dashboard

## Admin Dashboard

Cards

- Total Students
- Total Teachers
- Total Projects
- Pending Proposals
- Approved Projects
- Rejected Projects
- Late Submissions

Charts

- Approval Rate
- Teacher Workload
- Project Status Distribution

---

## Teacher Dashboard

Cards

- Assigned Students
- Pending Reviews
- Approved Projects
- Upcoming Meetings

Recent Activity

---

## Student Dashboard

Cards

Supervisor

Current Status

Upcoming Deadline

Recent Feedback

Progress

Timeline

---

# Meeting Logs

Teacher creates meeting.

Fields

Meeting Date

Agenda

Discussion

Assigned Tasks

Next Meeting

Student can view history.

---

# Calendar

Show

Submission Deadlines

Meetings

Presentation Dates

Milestones

---

# Announcements

Admin posts announcements.

Students and teachers can read.

---

# Reports

Generate

Student Report

Teacher Report

Department Report

Submission Report

Completion Report

Export

PDF

Excel

CSV

---

# Search

Search by

Student

Teacher

Project

Technology

Department

Status

Batch

Session

---

# Activity Logs

Store

User

Action

Timestamp

Old Value

New Value

IP Address

---

# File Storage

Supported Files

PDF

DOCX

PPTX

ZIP

PNG

JPG

Maximum upload size should be configurable.

---

# Email Notifications

Automatic emails

Proposal Submitted

Proposal Approved

Proposal Rejected

Revision Requested

Deadline Reminder

Final Submission Reminder

---

# Future AI Features

- AI proposal quality review
- AI grammar suggestions
- AI title suggestions
- AI methodology suggestions
- AI plagiarism warning
- AI project similarity detection
- AI automatic reviewer summary
- AI meeting summary
- AI chatbot for students

---

# Database Tables

users

roles

departments

academic_sessions

teachers

students

student_groups

student_group_members

supervisor_assignments

projects

project_members

proposal_versions

proposal_comments

proposal_replies

progress_reports

progress_comments

milestones

evaluations

meeting_logs

notifications

announcements

attachments

activity_logs

---

# Suggested Tech Stack

Frontend

- Next.js
- React
- TailwindCSS
- Shadcn UI

Backend

- Laravel 12
- Laravel Sanctum
- Laravel Queues

Database

- PostgreSQL

Storage

- AWS S3

Search

- PostgreSQL Full Text Search

Cache

- Redis

Email

- SMTP

Deployment

- Docker
- Nginx

---

# Development Phases

## Phase 1

Authentication

Role Management

Departments

Teachers

Students

Supervisor Assignment

---

## Phase 2

Proposal Submission

Proposal Review

Proposal Comments

Proposal Versioning

Notifications

---

## Phase 3

Progress Reports

Milestones

Meeting Logs

Calendar

---

## Phase 4

Final Submission

Evaluation

Reports

Analytics

---

## Phase 5

AI Features

Project Showcase

External Examiner Portal

Mobile App

---

# Non-Functional Requirements

- Responsive design
- Mobile friendly
- Secure authentication
- Role-based authorization
- Audit logging
- Automatic backups
- File versioning
- Scalable architecture
- RESTful API
- API documentation
- Unit testing
- Feature testing

---

# Nice-to-Have Features

- Dark Mode
- Two-Factor Authentication
- GitHub Integration
- Google Drive Integration
- Microsoft Teams Integration
- Zoom Meeting Integration
- Plagiarism Checker
- QR Code for final projects
- Public project showcase
- Student portfolio generation
- Supervisor workload balancing
- AI project recommendations
- Digital signatures
- Certificate generation