<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Anyone can apply for jobs
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'phone' => 'required|string|max:50',
            'country' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'zip' => 'required|string|max:20',
            'address' => 'required|string',
            'linkedin_url' => 'nullable|url|max:500',
            'cover_letter' => 'nullable|string|max:10000',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            
            // Work Experiences
            'experiences' => 'array',
            'experiences.*.company_name' => 'required|string|max:191',
            'experiences.*.is_current' => 'required|boolean',
            'experiences.*.start_date' => 'required|date|before_or_equal:today',
            'experiences.*.end_date' => [
                'nullable',
                'date',
                'after_or_equal:experiences.*.start_date',
            ],
            'experiences.*.description' => 'nullable|string|max:2000',
            
            // Education
            'educations' => 'array',
            'educations.*.degree_title' => 'required|string|max:191',
            'educations.*.institution' => 'required|string|max:191',
            'educations.*.is_current' => 'required|boolean',
            'educations.*.start_date' => 'required|date|before_or_equal:today',
            'educations.*.end_date' => [
                'nullable',
                'date',
                'after_or_equal:educations.*.start_date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Your full name is required.',
            'email.required' => 'A valid email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'phone.required' => 'A phone number is required.',
            'country.required' => 'Please specify your country.',
            'province.required' => 'Please specify your state/province.',
            'city.required' => 'Please specify your city.',
            'zip.required' => 'Please provide your ZIP/postal code.',
            'address.required' => 'Please provide your full address.',
            'linkedin_url.url' => 'Please provide a valid LinkedIn URL.',
            'resume.mimes' => 'Resume must be a PDF, DOC, or DOCX file.',
            'resume.max' => 'Resume file size must be less than 10MB.',
            'experiences.*.company_name.required' => 'Company name is required for each work experience.',
            'experiences.*.start_date.required' => 'Start date is required for each work experience.',
            'experiences.*.start_date.before_or_equal' => 'Start date cannot be in the future.',
            'experiences.*.end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'educations.*.degree_title.required' => 'Degree title is required for each education record.',
            'educations.*.institution.required' => 'Institution name is required for each education record.',
            'educations.*.start_date.required' => 'Start date is required for each education record.',
            'educations.*.start_date.before_or_equal' => 'Start date cannot be in the future.',
            'educations.*.end_date.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }
}
