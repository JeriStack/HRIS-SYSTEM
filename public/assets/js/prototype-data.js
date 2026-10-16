/**
 * Employee HRIS & Leave Management System
 * Synthetic Baseline Prototype Dataset (Matches MySQL Seed Records)
 */

const PrototypeData = {
    // Current Active Session (Simulated Role)
    currentUser: {
        id: 1,
        username: "admin",
        role: "admin", // 'admin' or 'employee'
        employeeId: 1,
        name: "Eleanor Vance",
        title: "Chief Executive Officer",
        department: "Executive Management",
        avatar: "EV"
    },

    mockEmployeeUser: {
        id: 4,
        username: "emp004",
        role: "employee",
        employeeId: 4,
        name: "David J. Cruz",
        title: "Senior Software Engineer",
        department: "Information Technology",
        avatar: "DC"
    },

    // Departments
    departments: [
        { id: 1, name: "Executive Management", description: "Executive leadership and corporate administration", employeesCount: 1 },
        { id: 2, name: "Human Resources", description: "Talent acquisition, employee welfare, and compliance", employeesCount: 2 },
        { id: 3, name: "Information Technology", description: "Software engineering, infrastructure, and IT support", employeesCount: 3 },
        { id: 4, name: "Finance & Accounting", description: "Corporate accounting, budgeting, and payroll tracking", employeesCount: 1 },
        { id: 5, name: "Marketing & Sales", description: "Brand management, communications, and customer outreach", employeesCount: 1 }
    ],

    // Job Positions
    positions: [
        { id: 1, departmentId: 1, name: "Chief Executive Officer", description: "Top executive officer" },
        { id: 2, departmentId: 2, name: "HR Director", description: "Head of Human Resources department" },
        { id: 3, departmentId: 2, name: "HR Officer", description: "Employee records and leave review coordinator" },
        { id: 4, departmentId: 3, name: "Senior Software Engineer", description: "Full-stack application architecture and backend lead" },
        { id: 5, departmentId: 3, name: "Junior Software Engineer", description: "Web development and maintenance" },
        { id: 6, departmentId: 3, name: "Systems Administrator", description: "Server infrastructure and network administration" },
        { id: 7, departmentId: 4, name: "Finance Manager", description: "Financial planning and reporting" },
        { id: 8, departmentId: 4, name: "Accountant", description: "Day-to-day corporate bookkeeping" },
        { id: 9, departmentId: 5, name: "Marketing Specialist", description: "Campaign management and digital outreach" }
    ],

    // Master Workforce Registry
    employees: [
        { id: 1, employeeNo: "EMP001", firstName: "Eleanor", middleName: "Rose", lastName: "Vance", email: "eleanor.vance@company.local", phone: "+63 917 111 2222", departmentId: 1, positionId: 1, department: "Executive Management", position: "Chief Executive Officer", dateHired: "2018-01-15", status: "Active", gender: "Female" },
        { id: 2, employeeNo: "EMP002", firstName: "Marcus", middleName: "Aurelio", lastName: "Tan", email: "marcus.tan@company.local", phone: "+63 918 222 3333", departmentId: 2, positionId: 2, department: "Human Resources", position: "HR Director", dateHired: "2019-03-01", status: "Active", gender: "Male" },
        { id: 3, employeeNo: "EMP003", firstName: "Sofia", middleName: "Maria", lastName: "Reyes", email: "sofia.reyes@company.local", phone: "+63 919 333 4444", departmentId: 2, positionId: 3, department: "Human Resources", position: "HR Officer", dateHired: "2020-07-15", status: "Active", gender: "Female" },
        { id: 4, employeeNo: "EMP004", firstName: "David", middleName: "James", lastName: "Cruz", email: "david.cruz@company.local", phone: "+63 920 444 5555", departmentId: 3, positionId: 4, department: "Information Technology", position: "Senior Software Engineer", dateHired: "2021-02-01", status: "Active", gender: "Male" },
        { id: 5, employeeNo: "EMP005", firstName: "Maria", middleName: "Clara", lastName: "Santos", email: "maria.santos@company.local", phone: "+63 921 555 6666", departmentId: 3, positionId: 5, department: "Information Technology", position: "Junior Software Engineer", dateHired: "2022-08-15", status: "Active", gender: "Female" },
        { id: 6, employeeNo: "EMP006", firstName: "Juan", middleName: "Carlos", lastName: "Dela Cruz", email: "juan.delacruz@company.local", phone: "+63 922 666 7777", departmentId: 3, positionId: 6, department: "Information Technology", position: "Systems Administrator", dateHired: "2021-11-10", status: "Active", gender: "Male" },
        { id: 7, employeeNo: "EMP007", firstName: "Beatrice", middleName: "Anne", lastName: "Gomez", email: "beatrice.gomez@company.local", phone: "+63 923 777 8888", departmentId: 4, positionId: 7, department: "Finance & Accounting", position: "Finance Manager", dateHired: "2020-01-10", status: "Active", gender: "Female" },
        { id: 8, employeeNo: "EMP008", firstName: "Ricardo", middleName: "Luis", lastName: "Mercado", email: "ricardo.mercado@company.local", phone: "+63 924 888 9999", departmentId: 5, positionId: 9, department: "Marketing & Sales", position: "Marketing Specialist", dateHired: "2021-05-15", status: "Inactive", gender: "Male" }
    ],

    // Leave Types
    leaveTypes: [
        { id: 1, name: "Vacation Leave", defaultDays: 15, status: "Active", description: "Annual personal rest and vacation" },
        { id: 2, name: "Sick Leave", defaultDays: 10, status: "Active", description: "Medical consult or illness recovery" },
        { id: 3, name: "Emergency Leave", defaultDays: 5, status: "Active", description: "Unplanned urgent family emergencies" },
        { id: 4, name: "Maternity Leave", defaultDays: 60, status: "Active", description: "Statutory maternity rest" },
        { id: 5, name: "Paternity Leave", defaultDays: 7, status: "Active", description: "Statutory paternity rest" }
    ],

    // Annual Leave Balances (Year 2026)
    leaveBalances: [
        // David Cruz (EMP004)
        { employeeId: 4, employeeNo: "EMP004", name: "David Cruz", typeId: 1, typeName: "Vacation Leave", year: 2026, allocated: 15, used: 3, remaining: 12 },
        { employeeId: 4, employeeNo: "EMP004", name: "David Cruz", typeId: 2, typeName: "Sick Leave", year: 2026, allocated: 10, used: 1, remaining: 9 },
        { employeeId: 4, employeeNo: "EMP004", name: "David Cruz", typeId: 3, typeName: "Emergency Leave", year: 2026, allocated: 5, used: 0, remaining: 5 },
        // Maria Santos (EMP005)
        { employeeId: 5, employeeNo: "EMP005", name: "Maria Santos", typeId: 1, typeName: "Vacation Leave", year: 2026, allocated: 15, used: 5, remaining: 10 },
        { employeeId: 5, employeeNo: "EMP005", name: "Maria Santos", typeId: 2, typeName: "Sick Leave", year: 2026, allocated: 10, used: 0, remaining: 10 },
        { employeeId: 5, employeeNo: "EMP005", name: "Maria Santos", typeId: 3, typeName: "Emergency Leave", year: 2026, allocated: 5, used: 0, remaining: 5 }
    ],

    // Leave Request Applications
    leaveRequests: [
        { id: 1, ref: "LR001", employeeId: 5, employeeNo: "EMP005", employeeName: "Maria Santos", department: "Information Technology", typeId: 1, typeName: "Vacation Leave", startDate: "2026-11-02", endDate: "2026-11-06", days: 5, reason: "Family vacation trip out of town", status: "Pending", submittedAt: "2026-10-01 09:15" },
        { id: 2, ref: "LR002", employeeId: 4, employeeNo: "EMP004", employeeName: "David Cruz", department: "Information Technology", typeId: 2, typeName: "Sick Leave", startDate: "2026-11-09", endDate: "2026-11-10", days: 2, reason: "Scheduled medical checkup and recovery", status: "Pending", submittedAt: "2026-10-02 11:30" },
        { id: 3, ref: "LR003", employeeId: 6, employeeNo: "EMP006", employeeName: "Juan Dela Cruz", department: "Information Technology", typeId: 1, typeName: "Vacation Leave", startDate: "2026-11-16", endDate: "2026-11-18", days: 3, reason: "Attending family reunion event", status: "Pending", submittedAt: "2026-10-03 14:00" },
        { id: 4, ref: "LR004", employeeId: 4, employeeNo: "EMP004", employeeName: "David Cruz", department: "Information Technology", typeId: 1, typeName: "Vacation Leave", startDate: "2026-08-10", endDate: "2026-08-12", days: 3, reason: "Annual rest and personal travel", status: "Approved", approver: "Marcus Tan", processedAt: "2026-08-01 10:00" },
        { id: 5, ref: "LR005", employeeId: 5, employeeNo: "EMP005", employeeName: "Maria Santos", department: "Information Technology", typeId: 1, typeName: "Vacation Leave", startDate: "2026-09-14", endDate: "2026-09-18", days: 5, reason: "Semester break vacation", status: "Approved", approver: "Marcus Tan", processedAt: "2026-09-05 15:45" },
        { id: 6, ref: "LR006", employeeId: 7, employeeNo: "EMP007", employeeName: "Beatrice Gomez", department: "Finance & Accounting", typeId: 1, typeName: "Vacation Leave", startDate: "2026-09-21", endDate: "2026-09-25", days: 5, reason: "Trip conflicts with corporate fiscal audit", status: "Rejected", approver: "Marcus Tan", processedAt: "2026-09-15 16:00" },
        { id: 7, ref: "LR007", employeeId: 4, employeeNo: "EMP004", employeeName: "David Cruz", department: "Information Technology", typeId: 3, typeName: "Emergency Leave", startDate: "2026-10-05", endDate: "2026-10-05", days: 1, reason: "Personal emergency resolved; no longer needed", status: "Cancelled", approver: null, processedAt: "2026-10-01 10:00" }
    ],

    // System Activity Logs (LIFO Stack Demonstration)
    activityLogs: [
        { id: 5, time: "2026-10-11 09:15:00", actor: "hr01 (Marcus Tan)", action: "APPROVE_LEAVE", entity: "leave_requests:4", desc: "Approved 3 days Vacation Leave for EMP004 (David Cruz)" },
        { id: 4, time: "2026-10-11 08:30:00", actor: "admin (Eleanor Vance)", action: "EMPLOYEE_CREATE", entity: "employees:8", desc: "Registered new employee record EMP008 (Ricardo Mercado)" },
        { id: 3, time: "2026-10-05 16:00:00", actor: "hr01 (Marcus Tan)", action: "REJECT_LEAVE", entity: "leave_requests:6", desc: "Rejected 5 days Vacation Leave for EMP007 (Beatrice Gomez)" },
        { id: 2, time: "2026-10-01 09:15:00", actor: "emp005 (Maria Santos)", action: "LEAVE_FILED", entity: "leave_requests:1", desc: "Filed Vacation Leave application for Nov 02-06 (5 days)" },
        { id: 1, time: "2026-10-01 08:00:00", actor: "SYSTEM", action: "SEED_DATABASE", entity: "SYSTEM:0", desc: "System initialized with baseline configuration" }
    ],

    // Notifications
    notifications: [
        { id: 1, title: "Leave Request Approved", message: "Your Vacation Leave request from 2026-08-10 to 2026-08-12 was approved by HR.", type: "success", isRead: true, time: "2 hours ago" },
        { id: 2, title: "Pending Leave Review", message: "Employee Maria Santos submitted a Vacation Leave request (5 days).", type: "info", isRead: false, time: "4 hours ago" },
        { id: 3, title: "Leave Request Rejected", message: "Your Vacation Leave application for Sep 21-25 was rejected due to audit schedules.", type: "warning", isRead: true, time: "3 days ago" },
        { id: 4, title: "System Notification", message: "Leave balances for fiscal year 2026 have been synchronized.", type: "info", isRead: true, time: "1 week ago" }
    ]
};
