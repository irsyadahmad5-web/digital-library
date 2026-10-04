<?php

return [
    'pdfinfo_binary' => env('PDFINFO_BINARY', 'pdfinfo'),
    'pdftocairo_binary' => env('PDFTOCAIRO_BINARY', 'pdftocairo'),
    'inspect_timeout_seconds' => (int) env('PDF_INSPECT_TIMEOUT_SECONDS', 45),
    'render_timeout_seconds' => (int) env('PDF_RENDER_TIMEOUT_SECONDS', 90),
    'preview_width' => (int) env('PDF_PREVIEW_WIDTH', 720),
    'preview_jpeg_quality' => (int) env('PDF_PREVIEW_JPEG_QUALITY', 85),
];
