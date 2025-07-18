@section('content')

    {!! $message !!}

    <div class="update-content">
        <div class="update-preloader text-center">
            <br>Loading, please wait...<br><br>
            <div class="preloader-spin"></div>
        </div>
    </div>
    <div class="panel update-message" style="display:none">
        <div class="heading"><i class="icon fas fa-cloud-download"></i> PawTunes Update</div>
        <div class="content">
            <div class="hide-on-progress">
                <span class="version-available"></span>
                <p>
                    Please <strong>backup</strong> your installation before proceeding! If in doubt, please contact support.
                </p>
                <div class="changelog latest mb-5" style="display:none">
                    Loading...
                </div>
                <a href="#" class="btn btn-success start-update">
                    <i class="fas icon fa-arrow-circle-right"></i> Start Update
                </a>
            </div>
            <div class="update-progress-bar" style="display:none;">
                <span>Please wait, do not interrupt this process...</span>
                <div class="progress-bar">
                    <div class="bar" style="width: 0;"><p>0%</p></div>
                    <
                </div>
                <pre class="m-0 p-0 update-progress">Starting update process, please wait...<br></pre>
            </div>
        </div>
    </div>
    <div class="panel update-history" style="display:none">
        <div class="heading">
            <a href="#" title="Expand all" class="pull-right d-flex toggle-all">
                <div class="fa-xs" style="display: inline-flex; flex-direction: column;">
                    <i class="fas fa-chevron-up"></i>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </a>
            <i class="fas fa-history"></i> Update History
        </div>
        <div class="content">
            <div class="changelog history">
                Loading...
            </div>
        </div>
    </div>
    <script type="text/javascript">
        window.loadInit = function () {

            let sse;
            let totalProgress = 0;

            let closeEvent = () => {
                sse.close();
            }

            function progressUI() {

                let bar = $('.update-progress-bar .bar');
                let text = $('.update-progress-bar .bar p');
                if (bar && text) {
                    bar.css('width', totalProgress + '%');
                    text.html(totalProgress + '%');

                    if (totalProgress > 49 && !text.hasClass('alter')) {
                        text.addClass('alter');
                    }
                }

                if (totalProgress === 100) {
                    $('.update-progress-bar').hide();
                }
            }

            /**
             * Helper to calculate in-between percentage
             */
            function mapPercentageToRange($percentage, $min, $max) {

                return Math.floor($min + ($percentage / 100) * ($max - $min));

            }

            /**
             * Parse changelog
             */
            function parseChangelog(data, noHeader = false) {

                let changeLog = '<div>' + markdown(data) + '</div>';

                // Remove title
                changeLog = changeLog.replace(/<h2>[^<\/]+<\/h2>/g, '');

                // Tweak Update Name
                changeLog = changeLog.replace(
                    /<h3>(.*?)<\/h3>/gi,
                    function (a, b, c) {
                        if (noHeader) return '';
                        return '</div><div class="changelog-heading"><h3>Release ' + b + ' <i class="fas pull-right fa-chevron-down"></i>' +
                            '</h3></div><div class="changelog-content">';
                    }
                );

                return changeLog;

            }


            /**
             * Start SSE
             * Global progress:
             * 0-10 = Connecting/starting
             * 10-50 = Downloading
             * 50-90 = Extracting
             * 90-100 = Post processing
             */
            function startUpdate(version) {

                sse = new EventSource('index.php?page=api&action=update&version=' + version);
                sse.addEventListener('update', (event) => {

                    if (event.data === 'close') {
                        closeEvent();
                        return;
                    }

                    let data = atob(event.data);
                    let curProgress = 0;

                    // Various messages
                    if (data.match(/downloading/i)) {

                        curProgress = $(data).find('.progress').text();
                        totalProgress = mapPercentageToRange(curProgress, 5, 50);

                    } else if (data.match(/extracting/i)) {

                        curProgress = $(data).find('.progress').text();
                        totalProgress = mapPercentageToRange(curProgress, 50, 95);

                    } else if (data.match(/completed/i)) {

                        totalProgress = 100;
                        $('.update-progress').after('<a class="btn btn-success mt-2" href="index.php?page=updates"><i class="fas icon fa-refresh"></i> Reload Page</a>');

                    }

                    progressUI();
                    $('.update-progress-bar').show();
                    $('.update-progress').html(data);

                })

                sse.onerror = () => {

                    $('.update-progress').html('<div class="text-danger">Update error occurred, please try again later. If the issue persists, please contact support.</div>');
                    sse.close();

                }

            }

            $.ajax({cache: false, url: 'index.php?page=api&action=update-check'}).then(function (data) {

                if (data.error && data.message) {

                    $('.update-content').html('<div class="alert alert-danger alert-icon"><div class="content">Sorry, checking for updates has failed, error message: ' +
                        '<pre>' + data.message + '</pre></div></div>');
                    return false;

                }

                if (!data.releases || data.releases.length <= 0) {

                    $('.update-content').html('{!!$panel->alert( 'Sorry but there are no updates available, please check again later!' )!!}');
                    return false;

                }

                const lastRelease = data.releases[0];
                const lastReleaseVersion = lastRelease.tag_name.replace('v', '')
                if (!shouldUpdate(lastReleaseVersion, version)) {

                    $('.update-content').html('{!!$panel->alert( 'Great news! You are running the latest version!', 'success' )!!}');

                } else {

                    let parsedDate = new Date(lastRelease.published_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });

                    // Add an alert message
                    $('.version-available').html(`Update version <b>${lastReleaseVersion}</b> released on <b>${parsedDate}</b> is ready.`);
                    $('.update-preloader').hide();
                    $('.update-message').show();

                    $('.start-update').on('click', function () {

                        if (!confirm('Are you sure you want to start the update?')) {
                            return false;
                        }

                        $(this).hide();
                        $('.update-progress-bar').show();
                        $('.hide-on-progress').hide();
                        startUpdate(lastRelease.tag_name);

                    })

                }

                // At the end, append changelog if available
                if (lastRelease.body) {

                    // Append to DOM
                    $('.changelog.latest').html(parseChangelog(lastRelease.body, true));

                } else {

                    // Display a message when the server doesn't respond with the change log
                    $('.changelog.latest').html('Sorry, latest change log is unavailable.');

                }

                // Show the change log
                $('.changelog.latest').show();
                $('.update-panel').removeClass('hidden');

            }).catch(function (xhr, status, error) {

                $('.update-content').html('{!!$panel->alert( 'Connection to the update server has failed! See the details below: <pre id="ajax-error"></pre>', 'error' )!!}');

                let log_text = "Unknown reason";

                // If some other error occurred, responded with error, write that instead
                if (typeof xhr !== 'undefined') {
                    log_text = xhr;
                }

                $('pre#ajax-error').html(log_text);

            });

            $.ajax({cache: false, url: 'index.php?page=api&action=update-history'}).then(function (data) {

                // Write a changelog from a file
                let historyLog = parseChangelog(data);

                $('.changelog.history').html(historyLog).show();
                $('.update-history').show();

                let changeLog = $('.changelog.history .changelog-heading')
                changeLog.on('click', function () {
                    $(this).toggleClass('open');
                    $(this).next('.changelog-content').toggleClass('open');
                })

                // Open first
                changeLog.first('.changelog-heading').trigger('click');

                // Toggle all
                $('.toggle-all').on('click', function (e) {

                    e.preventDefault();

                    let elm = $(this);

                    let changeLogContents = $('.changelog.history .changelog-content')
                    const allOpen = changeLogContents.toArray().every(el => $(el).hasClass('open'));

                    if (allOpen) {

                        changeLogContents.toArray().every(el => $(el).removeClass('open'))
                        elm.find('.fa-chevron-up').removeClass('fa-rotate-180');
                        elm.find('.fa-chevron-down').removeClass('fa-rotate-180');
                        elm.attr('title', 'Expand all');
                        return true;
                    }

                    changeLogContents.toArray().every(el => $(el).addClass('open'))
                    elm.find('.fa-chevron-up').addClass('fa-rotate-180');
                    elm.find('.fa-chevron-down').addClass('fa-rotate-180');
                    elm.attr('title', 'Collapse all');

                })

            });
        };

    </script>
@endsection
@include('template')