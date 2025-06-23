$(document).ready(function () {
    // Current page for pagination
    let currentPage = 1;
    const logsPerPage = 15;

    // Load logs initially
    loadLogs();

    // Refresh logs button
    $('#refresh-logs').click(function () {
        currentPage = 1;
        loadLogs();
    });

    // Search functionality
    $('#search-btn').click(function () {
        currentPage = 1;
        loadLogs();
    });

    // Handle Enter key in search
    $('#search-logs').keypress(function (e) {
        if (e.which === 13) {
            currentPage = 1;
            loadLogs();
        }
    });

    // Filter changes
    $('.log-level, #time-range').change(function () {
        currentPage = 1;
        loadLogs();
    });

    // Pagination click handler (delegated)
    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) {
            currentPage = page;
            loadLogs();
        }
    });

    // Function to load logs via AJAX
    function loadLogs() {
        const searchTerm = $('#search-logs').val();
        const timeRange = $('#time-range').val();

        // Get selected log levels
        const logLevels = [];
        $('.log-level:checked').each(function () {
            logLevels.push($(this).val());
        });

        // Show loading state
        $('#logs-container').html('<tr><td colspan="5" class="text-center">Loading logs...</td></tr>');

        // AJAX request
        $.ajax({
            url: 'ai_logs.php?action=get_ai_logs',
            type: 'GET',
            data: {
                page: currentPage,
                per_page: logsPerPage,
                search: searchTerm,
                levels: logLevels.join(','),
                time_range: timeRange
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderLogs(response.logs);
                    renderPagination(response.total, response.per_page, response.current_page);
                } else {
                    $('#logs-container').html('<tr><td colspan="5" class="text-center text-danger">' + response.message + '</td></tr>');
                }
            },
            error: function (xhr, status, error) {
                console.error("Error loading logs:", error);
                $('#logs-container').html('<tr><td colspan="5" class="text-center text-danger">Failed to load logs. Please try again.</td></tr>');
            }
        });
    }

    // Function to render logs
    function renderLogs(logs) {
        if (logs.length === 0) {
            $('#logs-container').html('<tr><td colspan="5" class="text-center">No logs found matching your criteria.</td></tr>');
            return;
        }

        let html = '';
        logs.forEach(log => {
            // Determine log level class
            let levelClass = '';
            if (log.action_taken && log.action_taken.includes('ERROR')) {
                levelClass = 'log-error';
            } else if (log.action_taken && log.action_taken.includes('WARNING')) {
                levelClass = 'log-warning';
            } else {
                levelClass = 'log-info';
            }

            html += `
                <tr>
                    <td>${formatDateTime(log.timestamp)}</td>
                    <td>${log.related_appointment_id || 'N/A'}</td>
                    <td class="${levelClass}">${log.action_taken || 'No action'}</td>
                    <td>${log.ai_reason || 'No reason provided'}</td>
                    <td class="log-details" title="${log.ai_reason || ''}">
                        ${truncate(log.ai_reason || '', 50)}
                    </td>
                </tr>
            `;
        });

        $('#logs-container').html(html);
    }

    // Function to render pagination
    function renderPagination(totalLogs, perPage, currentPage) {
        const totalPages = Math.ceil(totalLogs / perPage);

        if (totalPages <= 1) {
            $('#pagination').html('');
            return;
        }

        let html = '<ul class="pagination-list">';

        // Previous button
        if (currentPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a></li>`;
        }

        // Page numbers
        const maxVisiblePages = 5;
        let startPage, endPage;

        if (totalPages <= maxVisiblePages) {
            startPage = 1;
            endPage = totalPages;
        } else {
            const maxPagesBeforeCurrent = Math.floor(maxVisiblePages / 2);
            const maxPagesAfterCurrent = Math.ceil(maxVisiblePages / 2) - 1;

            if (currentPage <= maxPagesBeforeCurrent) {
                startPage = 1;
                endPage = maxVisiblePages;
            } else if (currentPage + maxPagesAfterCurrent >= totalPages) {
                startPage = totalPages - maxVisiblePages + 1;
                endPage = totalPages;
            } else {
                startPage = currentPage - maxPagesBeforeCurrent;
                endPage = currentPage + maxPagesAfterCurrent;
            }
        }

        // Add first page and ellipsis if needed
        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        // Add page numbers
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        // Add last page and ellipsis if needed
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }

        // Next button
        if (currentPage < totalPages) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage + 1}">Next</a></li>`;
        }

        html += '</ul>';
        $('#pagination').html(html);
    }

    // Helper function to format datetime
    function formatDateTime(timestamp) {
        if (!timestamp) return 'N/A';
        const date = new Date(timestamp);
        return date.toLocaleString();
    }

    // Helper function to truncate text
    function truncate(text, maxLength) {
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
});