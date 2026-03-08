<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeProjectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = [
            'id'        => $this->employee_id,
            'name'      => $this->name,
            'last_name' => $this->last_name,
            'salary'    => $this->salary,
            'country'   => $this->country,
        ];

        if ($this->country === 'USA') {
            $base['ssn']     = $this->ssn ? '***-**-' . substr($this->ssn, -4) : null;
            $base['address'] = $this->address;
        }

        if ($this->country === 'DEU') {
            $base['goal']                    = $this->goal;
            $base['tax_id']                  = $this->tax_id;
            $base['doc_work_permit']         = $this->doc_work_permit;
            $base['doc_tax_card']            = $this->doc_tax_card;
            $base['doc_health_insurance']    = $this->doc_health_insurance;
            $base['doc_social_security']     = $this->doc_social_security;
            $base['doc_employment_contract'] = $this->doc_employment_contract;
        }

        return $base;
    }
}
