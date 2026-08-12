<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_no' => $this->work_order_no,
            'title' => $this->title,
            'status' => $this->status,
            'priority' => $this->priority,
            'execution_way' => $this->execution_way,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'site' => $this->whenLoaded('site', fn () => $this->site ? [
                'id' => $this->site->id,
                'site_no' => $this->site->site_no,
                'address' => $this->site->address,
                'city' => $this->site->city,
            ] : null),
            'start_date' => $this->start_date?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'budget_amount' => $this->budget_amount,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
