@component('mail::layout')
# Welcome to ACL, {{ $name }}! 🎉

Your JAMB verification was **successful** and your academic identity is now confirmed.

**Verified Details:**
- **Name:** {{ $name }}
- **Institution:** {{ $institution }}
- **Programme:** {{ $programme }}

Your account is ready. You can now:
- Access your programme curriculum
- Enrol in courses for the current semester
- Track your academic progress
- Use ACLi AI study features (where entitled)

[Log in to your dashboard]({{ url('/login') }})

---

*This is an automated message from ACL (Anyone Can Learn). If you did not register, please contact support@aclacademy.me.*
@endcomponent