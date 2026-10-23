<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dataset preview
    |--------------------------------------------------------------------------
    |
    | Maximum number of vector features returned by the GeoJSON preview
    | endpoint. Larger datasets are returned as a truncated subset.
    |
    */

    'preview_vector_limit' => (int) env('PREVIEW_VECTOR_LIMIT', 10000),

];
