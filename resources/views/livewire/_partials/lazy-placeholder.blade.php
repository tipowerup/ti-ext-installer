<div class="row g-3">
    @for($i = 0; $i < 6; $i++)
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card tipowerup-installer__package-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--icon"></div>
                        <div class="ms-2 flex-grow-1">
                            <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--text-lg mb-1" style="width: 70%;"></div>
                            <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--text-sm" style="width: 40%;"></div>
                        </div>
                    </div>
                    <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--block mb-2"></div>
                    <div class="tipowerup-installer__skeleton tipowerup-installer__skeleton--btn"></div>
                </div>
            </div>
        </div>
    @endfor
</div>
