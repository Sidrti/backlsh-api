<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'deadline_status' => $this->deadline_status,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                    'profile_picture' => $this->creator->profile_picture,
                ];
            }),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'tasks_completed_count' => $this->when(isset($this->tasks_completed_count), $this->tasks_completed_count),
            'members_count' => $this->when(isset($this->members_count), $this->members_count),
            'todo_tasks_count' => $this->when(isset($this->todo_tasks_count), $this->todo_tasks_count),
            'in_progress_tasks_count' => $this->when(isset($this->in_progress_tasks_count), $this->in_progress_tasks_count),
            'done_tasks_count' => $this->when(isset($this->done_tasks_count), $this->done_tasks_count),
            'high_priority_tasks_count' => $this->when(isset($this->high_priority_tasks_count), $this->high_priority_tasks_count),
            'medium_priority_tasks_count' => $this->when(isset($this->medium_priority_tasks_count), $this->medium_priority_tasks_count),
            'low_priority_tasks_count' => $this->when(isset($this->low_priority_tasks_count), $this->low_priority_tasks_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
