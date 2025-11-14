<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
        ];
    }
}
