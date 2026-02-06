<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriber & Drug Data Explorer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.3/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
<main class="flex-grow-1">
        <div class="container">
<div class="container">
    <h1 class="mb-5 text-center">Prescriber & Drug Data Explorer</h1>

    <div class="row g-4">
        <!-- Prescriber Search -->
        <div class="col-lg-6">
            <div class="card p-4 shadow-sm h-100">
                <label for="prescriber" class="form-label fs-4 fw-bold">Search by Prescriber Last/Org Name</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-person text-primary"></i></span>
                    <input type="text" id="prescriber" class="form-control" placeholder="e.g., Khalil, Enkeshafi..." autocomplete="off">
                </div>
                <div class="mt-3 text-muted small">
                    Type 2+ characters to find prescribers. Select one to see their drug data.
                </div>
            </div>
        </div>

        <!-- Drug Search -->
        <div class="col-lg-6">
            <div class="card p-4 shadow-sm h-100">
                <label for="drug" class="form-label fs-4 fw-bold">Search by Drug (Brand or Generic)</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-capsule text-primary"></i></span>
                    <input type="text" id="drug" class="form-control" placeholder="e.g., Eliquis, Apixaban, Gabapentin..." autocomplete="off">
                </div>
                <div class="mt-3 text-muted small">
                    Type 2+ characters to find drugs. Select one to see nationwide prescriber data.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.3/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(function() {
    // Prescriber autocomplete
    $("#prescriber").autocomplete({
        source: function(request, response) {
            $("#prescriber").addClass("ui-autocomplete-loading");
            $.ajax({
                url: "suggest.php",
                dataType: "json",
                data: { term: request.term },
                success: function(data) {
                    response(data);
                },
                complete: function() {
                    $("#prescriber").removeClass("ui-autocomplete-loading");
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            if (ui.item) {
                window.location.href = "view.php?npi=" + ui.item.value;
            }
        }
    });

    // Drug autocomplete
    $("#drug").autocomplete({
        source: function(request, response) {
            $("#drug").addClass("ui-autocomplete-loading");
            $.ajax({
                url: "suggest_drugs.php",
                dataType: "json",
                data: { term: request.term },
                success: function(data) {
                    response($.map(data, function(item) {
                        return {
                            label: item.label,
                            value: item.brand,
                            generic: item.generic
                        };
                    }));
                },
                complete: function() {
                    $("#drug").removeClass("ui-autocomplete-loading");
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            if (ui.item) {
                let url = "drug.php?brand=" + encodeURIComponent(ui.item.value);
                if (ui.item.generic) {
                    url += "&generic=" + encodeURIComponent(ui.item.generic);
                }
                window.location.href = url;
            }
        }
    });
});
</script>

</div>
    </main>
<?php include 'footer.php'; ?>
</body>
</html>