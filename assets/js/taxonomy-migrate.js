(function ($) {
    "use strict";

    $(document).ready(function () {
        // Initialize Select2 for better search experience
        $(".taxonomy-select, .term-select").select2({
            width: "100%",
            placeholder: function () {
                return $(this).data("placeholder");
            },
        });

        // Handle operation type changes
        $('input[name="operation_type"]').on("change", function () {
            const operation = $('input[name="operation_type"]:checked').val();

            // Update button text based on operation
            if (operation === "move") {
                $("#migrate-button").text("Move Terms");
            } else if (operation === "add") {
                $("#migrate-button").text("Add Terms");
            } else if (operation === "remove") {
                $("#migrate-button").text("Remove Terms");
            }

            // Update button state based on form completion
            updateMigrateButton();
        });

        // Trigger change on page load to set initial state
        $('input[name="operation_type"]:checked').trigger("change");

        // Handle source taxonomy change
        $("#source_taxonomy").on("change", function () {
            const taxonomyId = $(this).val();
            const $termSelect = $("#source_term");

            // Auto-set the destination taxonomy to match the source taxonomy
            $("#destination_taxonomy").val(taxonomyId).trigger("change");

            if (!taxonomyId) {
                $termSelect.html('<option value="">' + "Select a taxonomy first" + "</option>");
                $termSelect.prop("disabled", true).trigger("change");
                return;
            }

            $termSelect.html('<option value="">' + taxonomyMigrate.loading + "</option>");
            $termSelect.prop("disabled", true).trigger("change");

            $.ajax({
                url: taxonomyMigrate.ajaxUrl,
                type: "POST",
                data: {
                    action: "get_taxonomy_terms",
                    taxonomy: taxonomyId,
                    nonce: taxonomyMigrate.nonce,
                },
                success: function (response) {
                    if (response.success && response.data) {
                        $termSelect.html('<option value="">' + "Select a term" + "</option>");

                        $.each(response.data, function (index, term) {
                            $termSelect.append('<option value="' + term.id + '">' + term.text + "</option>");
                        });

                        $termSelect.prop("disabled", false).trigger("change");
                    } else {
                        $termSelect.html('<option value="">' + "No terms found" + "</option>");
                        $termSelect.prop("disabled", true).trigger("change");
                    }
                },
                error: function () {
                    $termSelect.html('<option value="">' + "Error loading terms" + "</option>");
                    $termSelect.prop("disabled", true).trigger("change");
                },
            });
        });

        // Handle destination taxonomy change
        $("#destination_taxonomy").on("change", function () {
            const taxonomyId = $(this).val();
            const $termSelect = $("#destination_term");

            if (!taxonomyId) {
                $termSelect.html('<option value="">' + "Select a taxonomy first" + "</option>");
                $termSelect.prop("disabled", true).trigger("change");
                return;
            }

            $termSelect.html('<option value="">' + taxonomyMigrate.loading + "</option>");
            $termSelect.prop("disabled", true).trigger("change");

            $.ajax({
                url: taxonomyMigrate.ajaxUrl,
                type: "POST",
                data: {
                    action: "get_taxonomy_terms",
                    taxonomy: taxonomyId,
                    nonce: taxonomyMigrate.nonce,
                },
                success: function (response) {
                    if (response.success && response.data) {
                        $termSelect.html('<option value="">' + "Select a term" + "</option>");

                        $.each(response.data, function (index, term) {
                            $termSelect.append(
                                '<option value="' + term.id + '">' + term.text + " (" + term.slug + ")</option>",
                            );
                        });

                        $termSelect.prop("disabled", false).trigger("change");
                    } else {
                        $termSelect.html('<option value="">' + "No terms found" + "</option>");
                        $termSelect.prop("disabled", true).trigger("change");
                    }
                },
                error: function () {
                    $termSelect.html('<option value="">' + "Error loading terms" + "</option>");
                    $termSelect.prop("disabled", true).trigger("change");
                },
            });
        });

        // Enable/disable migrate button based on form completion
        function updateMigrateButton() {
            const sourceTaxonomy = $("#source_taxonomy").val();
            const sourceTerms = $("#source_term").val(); // Get multiple selected terms
            const destinationTaxonomy = $("#destination_taxonomy").val();
            const destinationTerm = $("#destination_term").val();
            const operationType = $('input[name="operation_type"]:checked').val();

            // For move, add, and remove operations, we need both source and destination
            if (sourceTaxonomy && sourceTerms.length > 0 && destinationTaxonomy && destinationTerm) {
                $("#migrate-button").prop("disabled", false);
            } else {
                $("#migrate-button").prop("disabled", true);
            }
        }

        $("#source_taxonomy, #source_term, #destination_taxonomy, #destination_term, input[name='operation_type']").on(
            "change",
            updateMigrateButton,
        );

        // Handle migration button click
        $("#migrate-button").on("click", function (e) {
            e.preventDefault();

            const $button = $(this);
            const $status = $("#migration-status");

            $button.prop("disabled", true);
            $status.html("<p>" + taxonomyMigrate.loading + "</p>").removeClass("hidden");

            const sourceTaxonomy = $("#source_taxonomy").val();
            const sourceTerms = $("#source_term")
                .val()
                .filter(term => Boolean(term)); // Filter out zero values
            const destinationTaxonomy = $("#destination_taxonomy").val();
            const destinationTerm = $("#destination_term").val();
            const deleteSourceTerm = $("#delete_source_term").is(":checked") ? 1 : 0;
            const useScheduler = $("#use_scheduler").is(":checked") ? 1 : 0;
            const operationType = $('input[name="operation_type"]:checked').val();

            $.ajax({
                url: taxonomyMigrate.ajaxUrl,
                type: "POST",
                data: {
                    action: "migrate_taxonomy_terms",
                    source_taxonomy: sourceTaxonomy,
                    source_terms: sourceTerms, // Send as array
                    destination_taxonomy: destinationTaxonomy,
                    destination_term: destinationTerm,
                    delete_source_term: deleteSourceTerm,
                    use_scheduler: useScheduler,
                    operation_type: operationType,
                    nonce: taxonomyMigrate.nonce,
                },
                success: function (response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success"><p>' + response.data.message + "</p></div>");

                        // Display results for immediate migration
                        if (!useScheduler && response.data.results !== undefined) {
                            let resultHtml = response.data.results
                                .map(result => "<p>Posts affected: " + result.posts_affected + "</p>")
                                .join("");

                            $("#result-content").html(resultHtml);
                            $("#result-container").removeClass("hidden");
                        } else {
                            // For scheduled migration
                            $("#result-container").addClass("hidden");
                        }

                        // Reset form
                        $("#source_term, #destination_term").val("").trigger("change");
                        $("#delete_source_term, #use_scheduler").prop("checked", false);
                    } else {
                        $status.html(
                            '<div class="notice notice-error"><p>' +
                                (response.data || taxonomyMigrate.error) +
                                "</p></div>",
                        );
                    }

                    $button.prop("disabled", false);
                },
                error: function () {
                    $status.html('<div class="notice notice-error"><p>' + taxonomyMigrate.error + "</p></div>");
                    $button.prop("disabled", false);
                },
            });
        });
    });
})(jQuery);
