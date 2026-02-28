<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalculationHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\CalculationHistory::class) ?? false;
    }

    public function rules(): array
    {
        $soilTypes = ['sandy', 'clay', 'loamy', 'silty', 'peaty', 'chalky'];

        $rules = [
            'saved_equation_id' => ['nullable', 'integer', 'exists:saved_equations,id'],
            'equation_name' => ['required', 'string', 'max:255'],
            'formula_snapshot' => ['required', 'string'],
            'inputs' => ['required', 'array'],
            'result' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->filled('saved_equation_id')) {
            $rules['inputs.soil_type'] = ['required', 'string', Rule::in($soilTypes)];
            $rules['inputs.*'] = ['nullable'];
        } else {
            $rules['inputs.seawall'] = ['required', 'numeric', 'min:0'];
            $rules['inputs.precipitation'] = ['required', 'numeric', 'min:0'];
            $rules['inputs.tropical_storm'] = ['required', 'numeric', 'min:0'];
            $rules['inputs.floods'] = ['required', 'numeric', 'min:0'];
            $rules['inputs.soil_type'] = ['required', 'string', Rule::in($soilTypes)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'inputs.seawall.required' => 'Seawall length is required.',
            'inputs.seawall.numeric' => 'Seawall length must be a number.',
            'inputs.seawall.min' => 'Seawall length must be 0 or more.',
            'inputs.precipitation.required' => 'Precipitation is required.',
            'inputs.precipitation.numeric' => 'Precipitation must be a number.',
            'inputs.precipitation.min' => 'Precipitation must be 0 or more.',
            'inputs.tropical_storm.required' => 'Tropical storm count is required.',
            'inputs.tropical_storm.numeric' => 'Tropical storm count must be a number.',
            'inputs.tropical_storm.min' => 'Tropical storm count must be 0 or more.',
            'inputs.floods.required' => 'Floods per year is required.',
            'inputs.floods.numeric' => 'Floods per year must be a number.',
            'inputs.floods.min' => 'Floods per year must be 0 or more.',
            'inputs.soil_type.required' => 'Soil type is required.',
            'inputs.soil_type.in' => 'Please select a valid soil type.',
        ];
    }
}
