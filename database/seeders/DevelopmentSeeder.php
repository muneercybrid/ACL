<?php

namespace Database\Seeders;

use App\Models\AcademicProgram;
use App\Models\AcademicSession;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseOfferingTarget;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\Level;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Organization & Hierarchy
        $uni = Organization::create(['name' => 'Demo University of Nigeria', 'slug' => 'demo-uni-nigeria', 'type' => 'university', 'code' => 'DUN', 'is_active' => true]);
        $faculty = Faculty::create(['organization_id' => $uni->id, 'name' => 'Faculty of Computing', 'slug' => 'faculty-of-computing', 'code' => 'FOC', 'is_active' => true]);

        $csDept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Department of Computer Science', 'slug' => 'computer-science', 'code' => 'CS', 'is_active' => true]);
        $cyDept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Department of Cybersecurity', 'slug' => 'cybersecurity', 'code' => 'CY', 'is_active' => true]);

        $csProg = AcademicProgram::create(['department_id' => $csDept->id, 'name' => 'B.Sc. Computer Science', 'slug' => 'bsc-computer-science', 'code' => 'BSC-CS', 'degree_type' => 'B.Sc.', 'duration_years' => 4, 'is_active' => true]);
        $cyProg = AcademicProgram::create(['department_id' => $cyDept->id, 'name' => 'B.Sc. Cybersecurity', 'slug' => 'bsc-cybersecurity', 'code' => 'BSC-CY', 'degree_type' => 'B.Sc.', 'duration_years' => 4, 'is_active' => true]);

        // 2. Academic Time Model
        // Dates are relative to today rather than hardcoded. Enrollments inherit
        // expires_at from the semester end date, and that expiry is enforced, so
        // fixed dates would silently lock every demo account out once passed.
        $sessionStart = now()->startOfMonth()->subMonth();
        $sem1End = $sessionStart->copy()->addMonths(5)->endOfMonth();
        $sessionEnd = $sessionStart->copy()->addMonths(11)->endOfMonth();
        $sessionLabel = $sessionStart->year.'/'.($sessionStart->year + 1);

        $session = AcademicSession::create(['name' => $sessionLabel, 'slug' => str_replace('/', '-', $sessionLabel), 'start_date' => $sessionStart, 'end_date' => $sessionEnd, 'is_active' => true]);
        $sem1 = Semester::create(['academic_session_id' => $session->id, 'name' => 'First Semester', 'slug' => 'first-semester', 'start_date' => $sessionStart, 'end_date' => $sem1End, 'is_active' => true]);
        Semester::create(['academic_session_id' => $session->id, 'name' => 'Second Semester', 'slug' => 'second-semester', 'start_date' => $sem1End->copy()->addDay(), 'end_date' => $sessionEnd, 'is_active' => false]);

        // 3. Levels
        $cs100 = Level::create(['academic_program_id' => $csProg->id, 'name' => '100 Level', 'code' => '100', 'sequence' => 1, 'is_active' => true]);
        Level::create(['academic_program_id' => $csProg->id, 'name' => '200 Level', 'code' => '200', 'sequence' => 2, 'is_active' => true]);
        $cy100 = Level::create(['academic_program_id' => $cyProg->id, 'name' => '100 Level', 'code' => '100', 'sequence' => 1, 'is_active' => true]);

        // 4. Courses
        $csc101 = Course::create(['code' => 'CSC101', 'title' => 'Introduction to Computer Science', 'slug' => 'csc101', 'credit_units' => 3, 'is_active' => true]);
        $cyb101 = Course::create(['code' => 'CYB101', 'title' => 'Introduction to Cybersecurity', 'slug' => 'cyb101', 'credit_units' => 3, 'is_active' => true]);

        // 5. Course Offerings & Targets
        $csc101Offering = CourseOffering::create(['course_id' => $csc101->id, 'semester_id' => $sem1->id, 'department_id' => $csDept->id, 'is_active' => true]);
        CourseOfferingTarget::create(['course_offering_id' => $csc101Offering->id, 'academic_program_id' => $csProg->id, 'level_id' => $cs100->id, 'is_mandatory' => true]);

        $cyb101Offering = CourseOffering::create(['course_id' => $cyb101->id, 'semester_id' => $sem1->id, 'department_id' => $cyDept->id, 'is_active' => true]);
        CourseOfferingTarget::create(['course_offering_id' => $cyb101Offering->id, 'academic_program_id' => $cyProg->id, 'level_id' => $cy100->id, 'is_mandatory' => true]);

        // 6. Users & Memberships
        User::create(['name' => 'Platform Admin', 'email' => 'admin@acl.local', 'password' => Hash::make('password')]);
        User::create(['name' => 'Dr. Amina Yusuf', 'email' => 'lecturer@acl.local', 'password' => Hash::make('password')]);
        $student = User::create(['name' => 'Muneer Cybrid', 'email' => 'student@acl.local', 'password' => Hash::make('password')]);

        OrganizationMembership::create(['organization_id' => $uni->id, 'user_id' => User::where('email', 'lecturer@acl.local')->first()->id, 'membership_type' => 'staff', 'status' => 'active', 'joined_at' => now()]);

        OrganizationMembership::create([
            'organization_id' => $uni->id,
            'user_id' => $student->id,
            'membership_type' => 'student',
            'status' => 'active',
            'joined_at' => now(),
            'academic_program_id' => $csProg->id,
            'current_level_id' => $cs100->id,
            'matric_number' => 'DUN/CS/2026/001',
        ]);

        // 7. Content Authoring (Slice 7)
        $chapter1 = Chapter::create([
            'course_offering_id' => $csc101Offering->id,
            'title' => 'Module 1: The Basics',
            'slug' => 'module-1-the-basics',
            'position' => 1,
        ]);

        $lesson1 = Lesson::create([
            'chapter_id' => $chapter1->id,
            'title' => 'What is Computer Science?',
            'slug' => 'what-is-computer-science',
            'position' => 1,
            'status' => 'published',
        ]);

        LessonBlock::create([
            'lesson_id' => $lesson1->id,
            'type' => 'text',
            'position' => 1,
            'data' => ['content' => 'Computer science is the study of computers and computational systems. Unlike electrical and computer engineers, computer scientists deal mostly with software and software systems; this includes their theory, design, development, and application.']
        ]);

        LessonBlock::create([
            'lesson_id' => $lesson1->id,
            'type' => 'video',
            'position' => 2,
            'data' => ['url' => 'https://www.youtube.com/embed/SzJ46Y_RnvA', 'title' => 'CS Intro Video']
        ]);
        
        LessonBlock::create([
            'lesson_id' => $lesson1->id,
            'type' => 'text',
            'position' => 3,
            'data' => ['content' => 'Principal areas of study include algorithms, data structures, programming languages, and software engineering.']
        ]);

        $this->command->info('Development, Academic & Content data seeded successfully!');
    }
}
