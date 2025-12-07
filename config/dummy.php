<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dummy Data for APIs
    |--------------------------------------------------------------------------
    | This file contains dummy/fallback responses for APIs when no real data
    | exists (e.g. new user, empty activity). Maintain all dummy JSON here.
    */

    'dashboard' => [
        "dummy" => "true",
        "status_code" => 1,
        "data" => [
            "total_time_worked" => "52h 40m",
            "total_productive_hours" => "19h 15m",
            "total_non_productive_hours" => "1h 30m",
            "total_neutral_hours" => "31h 55m",
            "team_working_trend" => [
                ["date" => "01-09-2025", "productive" => 2.5, "productive_tooltip" => "2h 30m", "nonproductive" => 0.2, "nonproductive_tooltip" => "12m", "neutral" => 4.0, "neutral_tooltip" => "4h"],
                ["date" => "02-09-2025", "productive" => 3.1, "productive_tooltip" => "3h 6m", "nonproductive" => 0.3, "nonproductive_tooltip" => "18m", "neutral" => 5.2, "neutral_tooltip" => "5h 12m"],
                ["date" => "03-09-2025", "productive" => 4.0, "productive_tooltip" => "4h", "nonproductive" => 0.1, "nonproductive_tooltip" => "6m", "neutral" => 6.3, "neutral_tooltip" => "6h 18m"],
                ["date" => "04-09-2025", "productive" => 2.7, "productive_tooltip" => "2h 42m", "nonproductive" => 0, "nonproductive_tooltip" => "0s", "neutral" => 3.5, "neutral_tooltip" => "3h 30m"],
                ["date" => "05-09-2025", "productive" => 3.4, "productive_tooltip" => "3h 24m", "nonproductive" => 0.4, "nonproductive_tooltip" => "24m", "neutral" => 4.8, "neutral_tooltip" => "4h 48m"],
                ["date" => "06-09-2025", "productive" => 2.1, "productive_tooltip" => "2h 6m", "nonproductive" => 0.2, "nonproductive_tooltip" => "12m", "neutral" => 2.9, "neutral_tooltip" => "2h 54m"],
                ["date" => "07-09-2025", "productive" => 1.2, "productive_tooltip" => "1h 12m", "nonproductive" => 0, "nonproductive_tooltip" => "0s", "neutral" => 2.1, "neutral_tooltip" => "2h 6m"]
            ],
            "today_online_member_count" => 3,
            "today_team_attendance" => 4,
            "top_members" => [
                ["user_id" => 0, "name" => "Alice Demo", "productive_time" => "6h 15m", "email" => "alicedemo@mail.com", "profile_picture" => "https://api.dicebear.com/8.x/avataaars/svg?seed=857943944322131dummy", "total_time" => "8h 30m", "productivity_percent" => 74],
                ["user_id" => 0, "name" => "Bob Demo", "productive_time" => "5h 30m", "email" => "bobdemo@mail.com", "profile_picture" => "https://api.dicebear.com/8.x/avataaars/svg?seed=85794w3944322132dummy", "total_time" => "6h 10m", "productivity_percent" => 50],
                ["user_id" => 0, "name" => "Charlie Demo", "productive_time" => "4h 50m", "email" => "charliedemo@mail.com", "profile_picture" => "https://api.dicebear.com/8.x/avataaars/svg?seed=8579s43944dummy", "total_time" => "3h 5m", "productivity_percent" => 20],
            ],
            "total_members" => 6,
            "week_productivity_percent" => [
                ["day" => "Mon", "date" => "01-09-2025", "productivity_percent" => 40, "productive_time" => "2h 30m", "nonproductive_time" => "12m", "neutral_time" => "4h", "total_time" => "6h 42m"],
                ["day" => "Tue", "date" => "02-09-2025", "productivity_percent" => 50, "productive_time" => "3h 6m", "nonproductive_time" => "18m", "neutral_time" => "5h 12m", "total_time" => "8h 36m"],
                ["day" => "Wed", "date" => "03-09-2025", "productivity_percent" => 80, "productive_time" => "4h", "nonproductive_time" => "6m", "neutral_time" => "6h 18m", "total_time" => "10h 24m"],
                ["day" => "Thu", "date" => "04-09-2025", "productivity_percent" => 35, "productive_time" => "2h 42m", "nonproductive_time" => "0s", "neutral_time" => "3h 30m", "total_time" => "6h 12m"],
                ["day" => "Fri", "date" => "05-09-2025", "productivity_percent" => 50, "productive_time" => "3h 24m", "nonproductive_time" => "24m", "neutral_time" => "4h 48m", "total_time" => "8h 36m"],
                ["day" => "Sat", "date" => "06-09-2025", "productivity_percent" => 30, "productive_time" => "2h 6m", "nonproductive_time" => "12m", "neutral_time" => "2h 54m", "total_time" => "5h 12m"],
                ["day" => "Sun", "date" => "07-09-2025", "productivity_percent" => 10, "productive_time" => "1h 12m", "nonproductive_time" => "0s", "neutral_time" => "2h 6m", "total_time" => "3h 18m"]
            ],
            "active_project_list" => [
                [
                    "project_id" => 5,
                    "project_name" => "E-Commerce Platform Upgrade",
                    "status" => "ACTIVE",
                    "time_spent" => "9h 32m",
                    "time_spent_seconds" => "34320",
                    "total_time_tracked" => "9h 32m",
                    "total_time_tracked_seconds" => "34320",
                    "task_done" => 4,
                    "task_assigned" => 18,
                    "time_progress_percentage" => 31,
                    "percentage_of_total_time" => 27,
                    "progress_percentage" => 22,
                    "start_date" => "22-11-2025",
                    "end_date" => "18-12-2025"
                ],
                [
                    "project_id" => 7,
                    "project_name" => "Website Redesign",
                    "status" => "ACTIVE",
                    "time_spent" => "12h 10m",
                    "time_spent_seconds" => "43800",
                    "total_time_tracked" => "12h 10m",
                    "total_time_tracked_seconds" => "43800",
                    "task_done" => 5,
                    "task_assigned" => 20,
                    "time_progress_percentage" => 42,
                    "percentage_of_total_time" => 35,
                    "progress_percentage" => 25,
                    "start_date" => "20-11-2025",
                    "end_date" => "10-12-2025"
                ],
                [
                    "project_id" => 12,
                    "project_name" => "Mobile App Development",
                    "status" => "ACTIVE",
                    "time_spent" => "7h 55m",
                    "time_spent_seconds" => "28500",
                    "total_time_tracked" => "7h 55m",
                    "total_time_tracked_seconds" => "28500",
                    "task_done" => 3,
                    "task_assigned" => 14,
                    "time_progress_percentage" => 28,
                    "percentage_of_total_time" => 22,
                    "progress_percentage" => 18,
                    "start_date" => "18-11-2025",
                    "end_date" => "28-12-2025"
                ],
            ]
        ],

    ],


    'top_processes' => [
        "dummy" => "true",
        "status_code" => 1,
        "data" => [
            "top_processes" => [
                "apps" => [
                    [
                        "id" => 1333,
                        "process_name" => "chrome",
                        "type" => "BROWSER",
                        "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/cDnWAX1MC3cLhb2MUPT87pKuhLyztI-metaY2hyb21lLnBuZw==-.png",
                        "total_seconds" => "74052",
                        "productivity_status" => "PRODUCTIVE",
                        "time_used_human" => "20h 34m",
                        "percentage_time" => 31.84,
                    ],
                    [
                        "id" => 1325,
                        "process_name" => "msedge",
                        "type" => "BROWSER",
                        "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/4S93kOZxqQyYYm14tTXMF08Lt3X4HW-metabWljcm9zb2Z0LnBuZw==-.png",
                        "total_seconds" => "70326",
                        "productivity_status" => "PRODUCTIVE",
                        "time_used_human" => "19h 32m",
                        "percentage_time" => 30.23,
                    ],
                    [
                        "id" => 1337,
                        "process_name" => "firefox",
                        "type" => "BROWSER",
                        "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/aWk1mnw0J7wRgKQ2dwM8ho2l0owoQ0-metaZmlyZWZveC5wbmc=-.png",
                        "total_seconds" => "26970",
                        "productivity_status" => "PRODUCTIVE",
                        "time_used_human" => "7h 29m",
                        "percentage_time" => 11.59,
                    ],
                    [
                        "id" => 1367,
                        "process_name" => "figma",
                        "type" => "APPLICATION",
                        "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/zZJUPC4OlDvIzxqBufQ1R2lpwjVeJx-metaZmlnbWEucG5n-.png",
                        "total_seconds" => "22362",
                        "productivity_status" => "PRODUCTIVE",
                        "time_used_human" => "6h 12m",
                        "percentage_time" => 9.61,
                    ],
                    [
                        "id" => 1329,
                        "process_name" => "code",
                        "type" => "APPLICATION",
                        "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                        "total_seconds" => "10712",
                        "productivity_status" => "PRODUCTIVE",
                        "time_used_human" => "2h 58m",
                        "percentage_time" => 4.61,
                    ],
                ],
                "websites" => [
                    [
                        "total_seconds" => "37423",
                        "productivity_status" => "PRODUCTIVE",
                        "process_name" => "freelancer.com",
                        "icon" => "uploads/favicons/freelancer.com.png",
                        "process_type" => "WEBSITE",
                        "icon_url" => "https://api.backlsh.com/public/storage/uploads/favicons/freelancer.com.png",
                        "time_used_human" => "10h 23m",
                        "percentage_time" => 16.09,
                    ],
                    [
                        "total_seconds" => "9489",
                        "productivity_status" => "NEUTRAL",
                        "process_name" => "chatgpt.com",
                        "icon" => "uploads/favicons/chatgpt.com.png",
                        "process_type" => "WEBSITE",
                        "icon_url" => "https://api.backlsh.com/public/storage/uploads/favicons/chatgpt.com.png",
                        "time_used_human" => "2h 38m",
                        "percentage_time" => 4.08,
                    ],
                    [
                        "total_seconds" => "7392",
                        "productivity_status" => "PRODUCTIVE",
                        "process_name" => "docs.google.com",
                        "icon" => "uploads/favicons/docs.google.com.png",
                        "process_type" => "WEBSITE",
                        "icon_url" => "https://api.backlsh.com/public/storage/uploads/favicons/docs.google.com.png",
                        "time_used_human" => "2h 3m",
                        "percentage_time" => 3.18,
                    ],
                ],
            ],
        ],
    ],

    'attendance' => [
        "dummy" => "true",
        "status_code" => 1,
        "data" => [
            "todays_users_attendance_list" => [
                [
                    "id" => 0,
                    "name" => "Alice",
                    "email" => "alicedemo@mail.com",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=alice",
                    "login_time" => "2025-09-13 13:20:38",
                    "total_seconds" => 5843,
                    "total_hours_human" => "1h 37m",
                    "attendance_status" => "Present",
                ],
                [
                    "id" => 0,
                    "name" => "Bob",
                    "email" => "bobdemo@mail.com",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=bob",
                    "login_time" => "2025-09-13 11:49:08",
                    "total_seconds" => 5184,
                    "total_hours_human" => "1h 26m",
                    "attendance_status" => "Present",
                ],
                [
                    "id" => 0,
                    "name" => "Charlie",
                    "email" => "charliedemo@mail.com",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=charlie",
                    "login_time" => "2025-09-13 11:16:31",
                    "total_seconds" => 9208,
                    "total_hours_human" => "2h 33m",
                    "attendance_status" => "Present",
                ],
                [
                    "id" => 0,
                    "name" => "Diana",
                    "email" => "dianademo@mail.com",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=diana",
                    "login_time" => "2025-09-13 00:04:06",
                    "total_seconds" => 10860,
                    "total_hours_human" => "3h 1m",
                    "attendance_status" => "Present",
                ],
                [
                    "id" => 0,
                    "name" => "Eve",
                    "email" => "evedemo@mail.com",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=eve",
                    "login_time" => null,
                    "total_seconds" => null,
                    "total_hours_human" => "0s",
                    "attendance_status" => "Absent",
                ],
                [
                    "id" => 0,
                    "name" => "Frank",
                    "email" => "frankdemo@mail.com",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=frank",
                    "login_time" => null,
                    "total_seconds" => null,
                    "total_hours_human" => "0s",
                    "attendance_status" => "Absent",
                ],
            ],
        ],
    ],

    "top_members" => [
        "dummy" => "true",
        "status_code" => 1,
        "data" => [
            ["user_id" => 0, "name" => "Alice Demo", "productive_time" => "6h 15m", "email" => "alicedemo@mail.com", "profile_picture" => "https://api.dicebear.com/8.x/avataaars/svg?seed=857943944322131dummy", "total_time" => "8h 30m", "productivity_percent" => 74],
            ["user_id" => 0, "name" => "Bob Demo", "productive_time" => "5h 30m", "email" => "bobdemo@mail.com", "profile_picture" => "https://api.dicebear.com/8.x/avataaars/svg?seed=85794w3944322132dummy", "total_time" => "6h 10m", "productivity_percent" => 50],
            ["user_id" => 0, "name" => "Charlie Demo", "productive_time" => "4h 50m", "email" => "charliedemo@mail.com", "profile_picture" => "https://api.dicebear.com/8.x/avataaars/svg?seed=8579s43944dummy", "total_time" => "3h 5m", "productivity_percent" => 20],
        ],
    ],
    "report" => [
        "dummy" => "true",
        "status_code" => 1,
        "data" => [
            "process" => [
                [
                    "process_id" => 1569,
                    "process_name" => "google chrome",
                    "productivity_status" => "NEUTRAL",
                    "type" => "APPLICATION",
                    "icon" => "uploads\/picons\/ZfwOtcYGlCc4BU8MnrKkTC09wIcSk9-metaY2hyb21lLnBuZw==-.png",
                    "total_seconds" => "7200",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/ZfwOtcYGlCc4BU8MnrKkTC09wIcSk9-metaY2hyb21lLnBuZw==-.png",
                    "total_time" => "2h",
                    "screenshots" => [
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo1",
                            "time" => "2025-09-17 11:22:15",
                            "productivity_status" => "NEUTRAL"
                        ],
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ],

                    ]
                ],
                [
                    "process_id" => 1566,
                    "process_name" => "safari",
                    "productivity_status" => "PRODUCTIVE",
                    "type" => "BROWSER",
                    "icon" => "uploads\/picons\/6Yi0lvoBVZu6mQgeb2qxXk4uMUHixQ-metac2FmYXJpLnBuZw==-.png",
                    "total_seconds" => "5400",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/6Yi0lvoBVZu6mQgeb2qxXk4uMUHixQ-metac2FmYXJpLnBuZw==-.png",
                    "total_time" => "1h 30m",
                    "screenshots" => [
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 10:15:03",
                            "productivity_status" => "PRODUCTIVE"
                        ]
                    ]
                ],
                [
                    "process_id" => 1564,
                    "process_name" => "whatsapp",
                    "productivity_status" => "NONPRODUCTIVE",
                    "type" => "APPLICATION",
                    "icon" => "uploads\/picons\/2Qo9Q7oY0X2GEBBG9S4jq80oLXBK6D-metad2hhdHNhcHAucG5n-.png",
                    "total_seconds" => "1800",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/2Qo9Q7oY0X2GEBBG9S4jq80oLXBK6D-metad2hhdHNhcHAucG5n-.png",
                    "total_time" => "30m",
                    "screenshots" => [
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo1",
                            "time" => "2025-09-17 14:45:28",
                            "productivity_status" => "NONPRODUCTIVE"
                        ],
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ]
                    ]
                ],
                [
                    "process_id" => 3852,
                    "process_name" => "microsoft teams",
                    "productivity_status" => "NEUTRAL",
                    "type" => "APPLICATION",
                    "icon" => null,
                    "total_seconds" => "3600",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/default\/default_process.png",
                    "total_time" => "1h",
                    "screenshots" => [
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo1",
                            "time" => "2025-09-17 09:30:45",
                            "productivity_status" => "NEUTRAL"
                        ],
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ],
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ],
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ]
                    ]
                ],
                [
                    "process_id" => 1329,
                    "process_name" => "code",
                    "productivity_status" => "PRODUCTIVE",
                    "type" => "APPLICATION",
                    "icon" => "uploads\/picons\/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                    "total_seconds" => "10800",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                    "total_time" => "3h",
                    "screenshots" => [
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ],
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ],
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ],
                        [
                            "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                            "time" => "2025-09-17 13:10:12",
                            "productivity_status" => "PRODUCTIVE"
                        ]
                    ]
                ],
                [
                    "process_id" => 1560,
                    "process_name" => "finder",
                    "productivity_status" => "NEUTRAL",
                    "type" => "APPLICATION",
                    "icon" => "uploads\/picons\/xaj7PH6LOQQqIBLNOC94YIdr1YKkT6-metabG9nbyAoMSkucG5n-.png",
                    "total_seconds" => "1200",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/xaj7PH6LOQQqIBLNOC94YIdr1YKkT6-metabG9nbyAoMSkucG5n-.png",
                    "total_time" => "20m",
                    "screenshots" => []
                ],
            ],
            "total_productive_hours" => "4h 30m",
            "user_attendance" => [
                "days_present" => 5,
                "total_days" => 7
            ],
            "working_trend" => [
                [
                    "date" => "11-09-2025",
                    "productive" => 2.1,
                    "productive_tooltip" => "2h 6m",
                    "nonproductive" => 0.4,
                    "nonproductive_tooltip" => "24m",
                    "neutral" => 1.5,
                    "neutral_tooltip" => "1h 30m"
                ],
                [
                    "date" => "12-09-2025",
                    "productive" => 3.2,
                    "productive_tooltip" => "3h 12m",
                    "nonproductive" => 0.2,
                    "nonproductive_tooltip" => "12m",
                    "neutral" => 1.8,
                    "neutral_tooltip" => "1h 48m"
                ],
                [
                    "date" => "15-09-2025",
                    "productive" => 1.8,
                    "productive_tooltip" => "1h 48m",
                    "nonproductive" => 0.5,
                    "nonproductive_tooltip" => "30m",
                    "neutral" => 2.2,
                    "neutral_tooltip" => "2h 12m"
                ],
                [
                    "date" => "16-09-2025",
                    "productive" => 2.5,
                    "productive_tooltip" => "2h 30m",
                    "nonproductive" => 0.3,
                    "nonproductive_tooltip" => "18m",
                    "neutral" => 1.0,
                    "neutral_tooltip" => "1h"
                ],
                [
                    "date" => "17-09-2025",
                    "productive" => 3.5,
                    "productive_tooltip" => "3h 30m",
                    "nonproductive" => 0.6,
                    "nonproductive_tooltip" => "36m",
                    "neutral" => 1.5,
                    "neutral_tooltip" => "1h 30m"
                ]
            ],
            "total_time_worked" => "7h 12m",
            "user_sub_activities" => [
                [
                    "process_id" => 1568,
                    "process_name" => "freelancer.in",
                    "productivity_status" => "NEUTRAL",
                    "type" => "WEBSITE",
                    "icon" => "uploads\/favicons\/freelancer.in.png",
                    "total_seconds" => "2400",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/favicons\/freelancer.in.png",
                    "total_time" => "40m"
                ],
                [
                    "process_id" => 1465,
                    "process_name" => "upwork.com",
                    "productivity_status" => "NEUTRAL",
                    "type" => "WEBSITE",
                    "icon" => "uploads\/favicons\/upwork.com.png",
                    "total_seconds" => "1800",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/favicons\/upwork.com.png",
                    "total_time" => "30m"
                ],
                [
                    "process_id" => 1412,
                    "process_name" => "claude.ai",
                    "productivity_status" => "NEUTRAL",
                    "type" => "WEBSITE",
                    "icon" => "uploads\/favicons\/claude.ai.png",
                    "total_seconds" => "900",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/favicons\/claude.ai.png",
                    "total_time" => "15m"
                ],
                [
                    "process_id" => 1360,
                    "process_name" => "chatgpt.com",
                    "productivity_status" => "NEUTRAL",
                    "type" => "WEBSITE",
                    "icon" => "uploads\/favicons\/chatgpt.com.png",
                    "total_seconds" => "1200",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/favicons\/chatgpt.com.png",
                    "total_time" => "20m"
                ],
                [
                    "process_id" => 3732,
                    "process_name" => "app.brevo.com",
                    "productivity_status" => "NEUTRAL",
                    "type" => "WEBSITE",
                    "icon" => "uploads\/favicons\/app.brevo.com.png",
                    "total_seconds" => "1500",
                    "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/favicons\/app.brevo.com.png",
                    "total_time" => "25m"
                ]
            ],
            "user_timeline" => [
                "2025-09-17" => [
                    [
                        "slot_start" => "2025-09-17 08:00:00",
                        "slot_end" => "2025-09-17 10:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15001,
                                "id" => 14001,
                                "start_datetime" => "2025-09-17 08:15:30",
                                "end_datetime" => "2025-09-17 08:45:15",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads\/picons\/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 1785,
                                "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "29m 45s"
                            ],
                            [
                                "user_activity_id" => 15002,
                                "id" => 14002,
                                "start_datetime" => "2025-09-17 09:00:00",
                                "end_datetime" => "2025-09-17 09:30:00",
                                "productivity_status" => "NEUTRAL",
                                "process_name" => "microsoft teams",
                                "type" => "APPLICATION",
                                "icon" => null,
                                "duration" => 1800,
                                "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/default\/default_process.png",
                                "time_used_human" => "30m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-17 10:00:00",
                        "slot_end" => "2025-09-17 12:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15010,
                                "id" => 14010,
                                "start_datetime" => "2025-09-17 10:05:00",
                                "end_datetime" => "2025-09-17 10:35:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "safari",
                                "type" => "BROWSER",
                                "icon" => "uploads\/picons\/6Yi0lvoBVZu6mQgeb2qxXk4uMUHixQ-metac2FmYXJpLnBuZw==-.png",
                                "duration" => 1800,
                                "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/6Yi0lvoBVZu6mQgeb2qxXk4uMUHixQ-metac2FmYXJpLnBuZw==-.png",
                                "time_used_human" => "30m"
                            ],
                            [
                                "user_activity_id" => 15011,
                                "id" => 14011,
                                "start_datetime" => "2025-09-17 11:00:00",
                                "end_datetime" => "2025-09-17 11:45:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads\/picons\/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 2700,
                                "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "45m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-17 12:00:00",
                        "slot_end" => "2025-09-17 14:00:00",
                        "status" => "NEUTRAL",
                        "activities" => [
                            [
                                "user_activity_id" => 15020,
                                "id" => 14020,
                                "start_datetime" => "2025-09-17 12:30:00",
                                "end_datetime" => "2025-09-17 13:00:00",
                                "productivity_status" => "NEUTRAL",
                                "process_name" => "google chrome",
                                "type" => "APPLICATION",
                                "icon" => "uploads\/picons\/ZfwOtcYGlCc4BU8MnrKkTC09wIcSk9-metaY2hyb21lLnBuZw==-.png",
                                "duration" => 1800,
                                "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/ZfwOtcYGlCc4BU8MnrKkTC09wIcSk9-metaY2hyb21lLnBuZw==-.png",
                                "time_used_human" => "30m"
                            ],
                            [
                                "user_activity_id" => 15021,
                                "id" => 14021,
                                "start_datetime" => "2025-09-17 13:15:00",
                                "end_datetime" => "2025-09-17 13:45:00",
                                "productivity_status" => "NONPRODUCTIVE",
                                "process_name" => "whatsapp",
                                "type" => "APPLICATION",
                                "icon" => "uploads\/picons\/2Qo9Q7oY0X2GEBBG9S4jq80oLXBK6D-metad2hhdHNhcHAucG5n-.png",
                                "duration" => 1800,
                                "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/2Qo9Q7oY0X2GEBBG9S4jq80oLXBK6D-metad2hhdHNhcHAucG5n-.png",
                                "time_used_human" => "30m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-17 14:00:00",
                        "slot_end" => "2025-09-17 16:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15030,
                                "id" => 14030,
                                "start_datetime" => "2025-09-17 14:00:00",
                                "end_datetime" => "2025-09-17 15:30:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads\/picons\/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 5400,
                                "icon_url" => "https:\/\/api.backlsh.com\/public\/storage\/uploads\/picons\/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "1h 30m"
                            ]
                        ]
                    ]
                ],
                "2025-09-18" => [
                    [
                        "slot_start" => "2025-09-18 08:00:00",
                        "slot_end" => "2025-09-18 10:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15101,
                                "id" => 14101,
                                "start_datetime" => "2025-09-18 08:10:00",
                                "end_datetime" => "2025-09-18 08:50:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 2400,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "40m"
                            ],
                            [
                                "user_activity_id" => 15102,
                                "id" => 14102,
                                "start_datetime" => "2025-09-18 09:00:00",
                                "end_datetime" => "2025-09-18 09:25:00",
                                "productivity_status" => "NEUTRAL",
                                "process_name" => "microsoft teams",
                                "type" => "APPLICATION",
                                "icon" => null,
                                "duration" => 1500,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/default/default_process.png",
                                "time_used_human" => "25m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-18 10:00:00",
                        "slot_end" => "2025-09-18 12:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15110,
                                "id" => 14110,
                                "start_datetime" => "2025-09-18 10:15:00",
                                "end_datetime" => "2025-09-18 11:00:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "safari",
                                "type" => "BROWSER",
                                "icon" => "uploads/picons/6Yi0lvoBVZu6mQgeb2qxXk4uMUHixQ-metac2FmYXJpLnBuZw==-.png",
                                "duration" => 2700,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/6Yi0lvoBVZu6mQgeb2qxXk4uMUHixQ-metac2FmYXJpLnBuZw==-.png",
                                "time_used_human" => "45m"
                            ],
                            [
                                "user_activity_id" => 15111,
                                "id" => 14111,
                                "start_datetime" => "2025-09-18 11:10:00",
                                "end_datetime" => "2025-09-18 11:55:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 2700,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "45m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-18 12:00:00",
                        "slot_end" => "2025-09-18 14:00:00",
                        "status" => "NEUTRAL",
                        "activities" => [
                            [
                                "user_activity_id" => 15120,
                                "id" => 14120,
                                "start_datetime" => "2025-09-18 12:20:00",
                                "end_datetime" => "2025-09-18 12:50:00",
                                "productivity_status" => "NEUTRAL",
                                "process_name" => "google chrome",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/ZfwOtcYGlCc4BU8MnrKkTC09wIcSk9-metaY2hyb21lLnBuZw==-.png",
                                "duration" => 1800,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ZfwOtcYGlCc4BU8MnrKkTC09wIcSk9-metaY2hyb21lLnBuZw==-.png",
                                "time_used_human" => "30m"
                            ],
                            [
                                "user_activity_id" => 15121,
                                "id" => 14121,
                                "start_datetime" => "2025-09-18 13:10:00",
                                "end_datetime" => "2025-09-18 13:40:00",
                                "productivity_status" => "NONPRODUCTIVE",
                                "process_name" => "whatsapp",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/2Qo9Q7oY0X2GEBBG9S4jq80oLXBK6D-metad2hhdHNhcHAucG5n-.png",
                                "duration" => 1800,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/2Qo9Q7oY0X2GEBBG9S4jq80oLXBK6D-metad2hhdHNhcHAucG5n-.png",
                                "time_used_human" => "30m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-18 14:00:00",
                        "slot_end" => "2025-09-18 16:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15130,
                                "id" => 14130,
                                "start_datetime" => "2025-09-18 14:15:00",
                                "end_datetime" => "2025-09-18 15:15:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 3600,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "1h"
                            ]
                        ]
                    ]
                ],

                "2025-09-19" => [
                    [
                        "slot_start" => "2025-09-19 08:00:00",
                        "slot_end" => "2025-09-19 10:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15201,
                                "id" => 14201,
                                "start_datetime" => "2025-09-19 08:05:00",
                                "end_datetime" => "2025-09-19 08:45:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 2400,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "40m"
                            ],
                            [
                                "user_activity_id" => 15202,
                                "id" => 14202,
                                "start_datetime" => "2025-09-19 09:00:00",
                                "end_datetime" => "2025-09-19 09:40:00",
                                "productivity_status" => "NEUTRAL",
                                "process_name" => "microsoft teams",
                                "type" => "APPLICATION",
                                "icon" => null,
                                "duration" => 2400,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/default/default_process.png",
                                "time_used_human" => "40m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-19 10:00:00",
                        "slot_end" => "2025-09-19 12:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15210,
                                "id" => 14210,
                                "start_datetime" => "2025-09-19 10:20:00",
                                "end_datetime" => "2025-09-19 10:55:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "safari",
                                "type" => "BROWSER",
                                "icon" => "uploads/picons/6Yi0lvoBVZu6mQgeb2qxXk4uMUHixQ-metac2FmYXJpLnBuZw==-.png",
                                "duration" => 2100,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/6Yi0lvoBVZu6mQgeb2qxXk4uMUHixQ-metac2FmYXJpLnBuZw==-.png",
                                "time_used_human" => "35m"
                            ],
                            [
                                "user_activity_id" => 15211,
                                "id" => 14211,
                                "start_datetime" => "2025-09-19 11:05:00",
                                "end_datetime" => "2025-09-19 11:50:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 2700,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "45m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-19 12:00:00",
                        "slot_end" => "2025-09-19 14:00:00",
                        "status" => "NEUTRAL",
                        "activities" => [
                            [
                                "user_activity_id" => 15220,
                                "id" => 14220,
                                "start_datetime" => "2025-09-19 12:30:00",
                                "end_datetime" => "2025-09-19 13:00:00",
                                "productivity_status" => "NEUTRAL",
                                "process_name" => "google chrome",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/ZfwOtcYGlCc4BU8MnrKkTC09wIcSk9-metaY2hyb21lLnBuZw==-.png",
                                "duration" => 1800,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ZfwOtcYGlCc4BU8MnrKkTC09wIcSk9-metaY2hyb21lLnBuZw==-.png",
                                "time_used_human" => "30m"
                            ],
                            [
                                "user_activity_id" => 15221,
                                "id" => 14221,
                                "start_datetime" => "2025-09-19 13:15:00",
                                "end_datetime" => "2025-09-19 13:40:00",
                                "productivity_status" => "NONPRODUCTIVE",
                                "process_name" => "whatsapp",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/2Qo9Q7oY0X2GEBBG9S4jq80oLXBK6D-metad2hhdHNhcHAucG5n-.png",
                                "duration" => 1500,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/2Qo9Q7oY0X2GEBBG9S4jq80oLXBK6D-metad2hhdHNhcHAucG5n-.png",
                                "time_used_human" => "25m"
                            ]
                        ]
                    ],
                    [
                        "slot_start" => "2025-09-19 14:00:00",
                        "slot_end" => "2025-09-19 16:00:00",
                        "status" => "PRODUCTIVE",
                        "activities" => [
                            [
                                "user_activity_id" => 15230,
                                "id" => 14230,
                                "start_datetime" => "2025-09-19 14:10:00",
                                "end_datetime" => "2025-09-19 15:40:00",
                                "productivity_status" => "PRODUCTIVE",
                                "process_name" => "code",
                                "type" => "APPLICATION",
                                "icon" => "uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "duration" => 5400,
                                "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png",
                                "time_used_human" => "1h 30m"
                            ]
                        ]
                    ]
                ],
            ],
            "projects_assigned_list" => [
                [
                    "project_id" => 8,
                    "project_name" => "Inventory Management System",
                    "status" => "ACTIVE",
                    "time_spent" => "6h 45m",
                    "time_spent_seconds" => "24300",
                    "total_time_tracked" => "6h 45m",
                    "total_time_tracked_seconds" => "24300",
                    "task_done" => 2,
                    "task_assigned" => 10,
                    "time_progress_percentage" => 45,
                    "start_date" => "21-11-2025",
                    "end_date" => "15-12-2025"
                ],
                [
                    "project_id" => 11,
                    "project_name" => "AI Chatbot Integration",
                    "status" => "ON_HOLD",
                    "time_spent" => "3h 20m",
                    "time_spent_seconds" => "12000",
                    "total_time_tracked" => "3h 20m",
                    "total_time_tracked_seconds" => "12000",
                    "task_done" => 1,
                    "task_assigned" => 8,
                    "time_progress_percentage" => 32,
                    "start_date" => "23-11-2025",
                    "end_date" => "20-12-2025"
                ],
                [
                    "project_id" => 14,
                    "project_name" => "Marketing Automation Dashboard",
                    "status" => "COMPLETED",
                    "time_spent" => "9h 05m",
                    "time_spent_seconds" => "32700",
                    "total_time_tracked" => "9h 05m",
                    "total_time_tracked_seconds" => "32700",
                    "task_done" => 5,
                    "task_assigned" => 14,
                    "time_progress_percentage" => 58,
                    "start_date" => "19-11-2025",
                    "end_date" => "25-12-2025"
                ],
            ]
        ]
    ],

    "realtime" => [
        "dummy" => "true",
        "status_code" => 1,
        "data" => [
            "today_online_member_count" => 4,
            "realtime_update" => [
                [
                    "user_id" => 0,
                    "name" => "Alex Demo",
                    "process_name" => "code",
                    "productivity_status" => "PRODUCTIVE",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=857943987",
                    "process_id" => 1329,
                    "process_type" => "APPLICATION",
                    "last_working_datetime" => "2025-09-17T17:07:25.000000Z",
                    "status" => "ONLINE",
                    "productive_ratio" => 0.87,
                    "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/ch0CaK5cX5kUtyB9UADNN2PQEvj4MR-metadmlzdWFsLXN0dWRpby5wbmc=-.png"
                ],
                [
                    "user_id" => 0,
                    "name" => "Maria Demo",
                    "process_name" => "figma",
                    "productivity_status" => "PRODUCTIVE",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=85794394",
                    "process_id" => 1367,
                    "process_type" => "APPLICATION",
                    "last_working_datetime" => "2025-09-17T15:52:46.000000Z",
                    "status" => "ONLINE",
                    "productive_ratio" => 0.99,
                    "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/zZJUPC4OlDvIzxqBufQ1R2lpwjVeJx-metaZmlnbWEucG5n-.png"
                ],
                [
                    "user_id" => 0,
                    "name" => "John Demo",
                    "process_name" => "-1",
                    "productivity_status" => "NEUTRAL",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=857943986",
                    "process_id" => 1340,
                    "process_type" => "APPLICATION",
                    "last_working_datetime" => "2025-09-17T15:24:15.000000Z",
                    "status" => "ONLINE",
                    "productive_ratio" => 0,
                    "icon_url" => "https://api.backlsh.com/public/storage/uploads/default/default_process.png"
                ],
                [
                    "user_id" => 0,
                    "name" => "Sophia Demo",
                    "process_name" => "chrome",
                    "productivity_status" => "PRODUCTIVE",
                    "profile_picture" => "https://api.backlsh.com/public/storage/uploads/profile/26439_1752584180_Snapchat-2010853802.jpg",
                    "process_id" => 1333,
                    "process_type" => "BROWSER",
                    "last_working_datetime" => "2025-09-17T15:00:48.000000Z",
                    "status" => "ONLINE",
                    "productive_ratio" => 0.84,
                    "icon_url" => "https://api.backlsh.com/public/storage/uploads/picons/cDnWAX1MC3cLhb2MUPT87pKuhLyztI-metaY2hyb21lLnBuZw==-.png"
                ],
                [
                    "user_id" => 0,
                    "name" => "David Demo",
                    "process_name" => "lockapp",
                    "productivity_status" => "NONPRODUCTIVE",
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=857943980",
                    "process_id" => 1356,
                    "process_type" => "APPLICATION",
                    "last_working_datetime" => "2025-09-17T12:50:35.000000Z",
                    "status" => "OFFLINE",
                    "productive_ratio" => 0.81,
                    "icon_url" => "https://api.backlsh.com/public/storage/uploads/default/default_process.png"
                ],
                [
                    "user_id" => 0,
                    "name" => "Emma Demo",
                    "process_name" => null,
                    "productivity_status" => null,
                    "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=857",
                    "process_id" => null,
                    "process_type" => null,
                    "last_working_datetime" => null,
                    "status" => "OFFLINE",
                    "productive_ratio" => 0,
                    "icon_url" => "https://api.backlsh.com/public/storage/uploads/default/default_process.png"
                ]
            ],
            "non_productive_users_count" => 2,
            "member_present_today" => 5
        ]
    ],
    "screenshots" => [
        "dummy" => "true",
        "status_code" => 1,
        "data" => [
            "screenshots" => [
                [
                    "id" => 1683,
                    "process_id" => 1335,
                    "website_url" => "",
                    "user_id" => 38,
                    "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo1",
                    "created_at" => "2025-09-14 17:13:10",
                    "updated_at" => "2025-09-14 17:13:10",
                    "process_name" => "code",
                    "type" => "APPLICATION",
                ],
                [
                    "id" => 1684,
                    "process_id" => 1333,
                    "website_url" => "",
                    "user_id" => 38,
                    "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo2",
                    "created_at" => "2025-09-14 17:18:15",
                    "updated_at" => "2025-09-14 17:18:15",
                    "process_name" => "code",
                    "type" => "APPLICATION",
                ],
                [
                    "id" => 1685,
                    "process_id" => 1369,
                    "website_url" => "",
                    "user_id" => 38,
                    "screenshot_path" => "https://ik.imagekit.io/backlsh26/demo/demo1",
                    "created_at" => "2025-09-14 17:23:19",
                    "updated_at" => "2025-09-14 17:23:19",
                    "process_name" => "code",
                    "type" => "APPLICATION",
                ],
            ],
        ],
    ],
    "team_member" =>[[
            "id" => 0,
            "name" => "Alex Demo",
            "email" => "alex@mail.com",
            "profile_picture" => "https://api.dicebear.com/7.x/avataaars/svg?seed=85794394",
            "stealth_mode" => 0,
            "activity_status" => "ACTIVE"
        ]],
];
