<?php

namespace App\Helpers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class NotificationHelper
{
    public static function notifyTaskAssigned(Task $task, Project $project, ?User $assigner): void
    {
        if (!$task->assignee_id) {
            return;
        }

        $assignerName = $assigner ? $assigner->name : 'Someone';

        Helper::sendNotification(
            userId: $task->assignee_id,
            type: 'TASK_ASSIGNED',
            title: 'New Task Assigned',
            message: '<strong>' . e($assignerName) . '</strong> assigned you a task in project <strong>' . e($project->name) . '</strong>.',
            triggeredBy: $assigner?->id,
            relatedType: 'projects',
            relatedId: $project->id,
            emailDetails: [
                'Task' => $task->name,
                'Project' => $project->name,
                'Priority' => $task->priority ?? 'MEDIUM',
                'Due Date' => $task->due_date ? $task->due_date->format('d M Y') : 'No deadline',
            ],
            actionUrl: config('app.website_url') . '/projects/' . $project->id,
            actionText: 'View Task',
            sendEmail: true
        );
    }

    public static function notifyTaskStatusChanged(Task $task, Project $project, ?User $changedBy, ?string $oldStatus, string $newStatus): void
    {
        $changedByName = $changedBy ? $changedBy->name : 'Someone';
        $message = '<strong>' . e($changedByName) . '</strong> changed the status of task <strong>' . e($task->name) . '</strong> to <strong>' . $newStatus . '</strong>.';

        $emailDetails = [
            'Task' => $task->name,
            'Project' => $project->name,
            'New Status' => $newStatus,
        ];

        if ($oldStatus) {
            $emailDetails['Previous Status'] = $oldStatus;
        }

        $notifyUserIds = [];

        if ($project->created_by && $project->created_by !== $changedBy?->id) {
            $notifyUserIds[] = $project->created_by;
        }

        if ($task->assignee_id && $task->assignee_id !== $changedBy?->id && !in_array($task->assignee_id, $notifyUserIds)) {
            $notifyUserIds[] = $task->assignee_id;
        }

        foreach ($notifyUserIds as $userId) {
            Helper::sendNotification(
                userId: $userId,
                type: 'TASK_STATUS_CHANGED',
                title: 'Task Status Updated',
                message: $message,
                triggeredBy: $changedBy?->id,
                relatedType: 'projects',
                relatedId: $project->id,
                emailDetails: $emailDetails,
                actionUrl: config('app.website_url') . '/projects/' . $project->id,
                actionText: 'View Task',
                sendEmail: true
            );
        }
    }

    public static function notifyProjectAssignedToMembers(Project $project, array $memberIds, ?User $creator): void
    {
        if (!$creator) {
            return;
        }

        foreach ($memberIds as $memberId) {
            if ($memberId !== $creator->id) {
                Helper::sendNotification(
                    userId: $memberId,
                    type: 'PROJECT_ASSIGNED',
                    title: 'Added to Project',
                    message: '<strong>' . e($creator->name) . '</strong> added you to the project <strong>' . e($project->name) . '</strong>.',
                    triggeredBy: $creator->id,
                    relatedType: 'projects',
                    relatedId: $project->id,
                    emailDetails: [
                        'Project' => $project->name,
                        'Status' => $project->status,
                        'Start Date' => $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('d M Y') : 'Not set',
                        'End Date' => $project->end_date ? \Carbon\Carbon::parse($project->end_date)->format('d M Y') : 'Not set',
                    ],
                    actionUrl: config('app.website_url') . '/projects/' . $project->id,
                    actionText: 'View Project',
                    sendEmail: true
                );
            }
        }
    }

    public static function notifyProjectMemberAdded(Project $project, int $userId, ?User $creator): void
    {
        if (!$creator) {
            return;
        }

        Helper::sendNotification(
            userId: $userId,
            type: 'PROJECT_MEMBER_ADDED',
            title: 'Added to Project',
            message: '<strong>' . e($creator->name) . '</strong> added you to the project <strong>' . e($project->name) . '</strong>.',
            triggeredBy: $creator->id,
            relatedType: 'projects',
            relatedId: $project->id,
            emailDetails: [
                'Project' => $project->name,
                'Added By' => $creator->name,
            ],
            actionUrl: config('app.website_url') . '/projects/' . $project->id,
            actionText: 'View Project',
            sendEmail: true
        );
    }
}
