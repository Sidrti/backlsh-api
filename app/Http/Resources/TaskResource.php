<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $totalDurationInSeconds = $this->whenLoaded('userActivities', function () {
            return $this->userActivities->reduce(function ($carry, $activity) {
                if ($activity->start_datetime && $activity->end_datetime) {
                    $startTime = Carbon::parse($activity->start_datetime);
                    $endTime = Carbon::parse($activity->end_datetime);
                    return $carry + $endTime->diffInSeconds($startTime);
                }
                return $carry;
            }, 0);
        });

        $totalTimeSpent = $this->whenLoaded('userActivities', function () use ($totalDurationInSeconds) {
            if ($totalDurationInSeconds === null) return null;
            $hours = floor($totalDurationInSeconds / 3600);
            $minutes = floor(($totalDurationInSeconds % 3600) / 60);
            $seconds = $totalDurationInSeconds % 60;
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        });
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'deadline_status' => $this->deadline_status,
            'assignee_id' => $this->assignee_id,
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'status' => $this->project->status,
                ];
            }),
            'assignee' => $this->whenLoaded('assignee', function () {
                return $this->assignee ? [
                    'id' => $this->assignee->id,
                    'name' => $this->assignee->name,
                    'email' => $this->assignee->email,
                    'profile_picture' => $this->assignee->profile_picture,
                ] : null;
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'time_logs' => $this->whenLoaded('userActivities', function () {
                return $this->userActivities->map(function ($activity) {
                    if (!$activity->start_datetime || !$activity->end_datetime) {
                        return null;
                    }
                    $startTime = Carbon::parse($activity->start_datetime);
                    $endTime = Carbon::parse($activity->end_datetime);
                    $duration = $endTime->diffAsCarbonInterval($startTime);

                    return [
                        'start_time' => $startTime->format('Y-m-d H:i:s'),
                        'end_time' => $endTime->format('Y-m-d H:i:s'),
                        'duration' => $duration->format('%H:%I:%S'),
                    ];
                })->filter();
            }),
            'total_time_spent' => $totalTimeSpent,
        ];
    }
}
