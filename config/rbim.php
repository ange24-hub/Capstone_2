<?php

use App\Models\DocumentRequest;

return [
    // Fees are configurable because they must follow the municipality's approved schedule.
    'document_fees' => [
        DocumentRequest::TYPE_INDIGENCY => (float) env('DOCUMENT_FEE_INDIGENCY', 0),
        DocumentRequest::TYPE_CLEARANCE => (float) env('DOCUMENT_FEE_CLEARANCE', 0),
        DocumentRequest::TYPE_RESIDENCY => (float) env('DOCUMENT_FEE_RESIDENCY', 0),
        DocumentRequest::TYPE_BUSINESS_CLEARANCE => (float) env('DOCUMENT_FEE_BUSINESS_CLEARANCE', 0),
    ],
];
