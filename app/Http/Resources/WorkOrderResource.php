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
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'start_date' => $this->start_date?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'budget_amount' => $this->budget_amount,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
