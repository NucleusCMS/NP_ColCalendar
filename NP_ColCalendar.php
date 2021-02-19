<?php

/**
 * This plugin can be used to insert a calendar on your page
 *
 * History:
 *   v0.9a Calendar views horizontally whithout a day of the week.
 * 
 * For today highlight added this in default.css
 *   table.calendar td.today { background-color: green;}
 *
 * Some example of customing the calendar
 *   table.calendar {
 *     font-size: small;
 *     color: black;
 *   }
 *   tr.calendardateheaders {
 *     font-size: small;
 *     color: red;
 *   }
 *   td.days {
 *     text-align: center;
 *   }
 *   td.today {
 *     text-align: center;
 *     color: green;
 *     background-color: whitesmoke;
 *   }
 */

if (!function_exists('sql_table')) {
    function sql_table($name)
    {
        return 'nucleus_' . $name;
    }
}

class NP_ColCalendar extends NucleusPlugin
{

    /**
     * Plugin data to be shown on the plugin list
     */
    function getName()
    {
        return 'Calendar Plugin Customized';
    }
    function getAuthor()
    {
        return 'karma / roel / jhoover / admun / hcgtv / charlie';
    }
    function getURL()
    {
        return 'http://nucleuscms.org/';
    }
    function getVersion()
    {
        return '0.82';
    }
    function getDescription()
    {
        return 'This plugin can be called from within skins to insert a calender on your site, by using &lt;%Calendar%&gt;.';
    }

    function supportsFeature($feature)
    {
        switch ($feature) {
            case 'SqlTablePrefix':
                return 1;
            default:
                return 0;
        }
    }

    /**
     * On plugin install, three options are created
     */
    function install()
    {
        // create some options
        $this->createOption('Locale', 'Language (locale) to use', 'text', 'ja_JP');
        $this->createOption('LinkAll', 'Create links for all days (even those that do not have posts?)', 'yesno', 'no');
        $this->createOption('JustCal', 'Display a calendar with no link?  (override create links for all days above)', 'yesno', 'no');
        $this->createOption('Summary', 'Summary text for the calendar table', 'text', 'Monthly calendar with links to each day\'s posts');
        $this->createOption('prevm', 'Label for previous month', 'text', '&lt;');
        $this->createOption('nextm', 'Label for next month', 'text', '&gt;');
    }

    /**
     * skinvar parameters:
     *      - blogname (optional)
     */
    function doSkinVar($skinType, $view = 'all', $blogName = '')
    {
        global $manager, $blog, $CONF, $archive;

        /*
        find out which blog to use:
        1. try the blog chosen in skinvar parameter
        2. try to use the currently selected blog
        3. use the default blog
	    */
        if ($blogName) {
            $b = &$manager->getBlog(getBlogIDFromName($params[2]));
        } else if ($blog) {
            $b = &$blog;
        } else {
            $b = &$manager->getBlog($CONF['DefaultBlog']);
        }

        /*
        select which month to show
        - for archives: use that month
        - otherwise: use current month
	    */
        switch ($skinType) {
            case 'archive':
                sscanf($archive, '%d-%d-%d', $y, $m, $d);
                $time = mktime(0, 0, 0, $m, 1, $y);
                break;
            default:
                $time = $b->getCorrectTime(time());
        }

        /*   Set $category if $view = 'limited' 
		This means only items from the specified category 
		will be displayed in the calendar.
		Defaults to show all categories in calendar. 
	    */
        $category = ($view == 'limited') ? $blog->getSelectedCategory() : 0;

        $this->_drawCalendar($time, $b, $this->getOption('LinkAll'), $category);
    }

    /**
     * This function draws the actual calendar as a table
     */
    function _drawCalendar($timestamp, &$blog, $linkall, $category)
    {
        $blogid = $blog->getID();

        // set correct locale
        setlocale(LC_TIME, $this->getOption('Locale'));

        // get year/month etc
        $date = getDate($timestamp);

        $month = $date['mon'];
        $year = $date['year'];

        // get previous year-month
        $last_month = $month - 1;
        $last_year = $year;
        if (!checkdate($last_month, 1, $last_year)) {
            $last_month += 12;
            $last_year--;
        }

        if ($last_month < 10) {
            $last_month = "0" . $last_month;
        } else {
            $last_month >= 10;
            $last_month = $last_month;
        }

        // get the next year-month
        $next_month = $month + 1;
        $next_year = $year;
        if (!checkdate($next_month, 1, $next_year)) {
            $next_year++;
            $next_month -= 12;
        }

        if ($next_month < 10) {
            $next_month = "0" . $next_month;
        } else {
            $next_month >= 10;
            $next_month = $next_month;
        }

        $nolink = $this->getOption('JustCal');

        // find out for which days we have posts
        if ($linkall == 'no' && $nolink == 'no') {
            $days = array();
            $timeNow = $blog->getCorrectTime();
            if ($category != 0) {
                $res = sql_query('SELECT DAYOFMONTH(itime) as day FROM ' . sql_table('item') . ' WHERE icat=' . $category . ' and MONTH(itime)=' . $month . ' and YEAR(itime)=' . $year . ' and iblog=' . $blogid . ' and idraft=0 and UNIX_TIMESTAMP(itime)<' . $timeNow . ' GROUP BY day');
            } else {
                $res = sql_query('SELECT DAYOFMONTH(itime) as day FROM ' . sql_table('item') . ' WHERE MONTH(itime)=' . $month . ' and YEAR(itime)=' . $year . ' and iblog=' . $blogid . ' and idraft=0 and UNIX_TIMESTAMP(itime)<' . $timeNow . ' GROUP BY day');
            }

            while ($o = mysql_fetch_object($res)) {
                $days[$o->day] = 1;
            }
        }

        $prev = $this->getOption('prevm');
        $next = $this->getOption('nextm');

        // draw header
        if ($nolink == "yes") {
?>
<!-- kalendar start -->
<table class="calendar" summary="<?php echo htmlspecialchars($this->getOption('Summary')) ?>">
    <caption>
        <?php echo strftime('%Y-%m', $timestamp); ?></a>
    </caption>
    <tr class="calendardateheaders">
    <?php
} else {
    ?>

        <!-- kalendar start -->
        <table class="calendarbody" align="center">
            <tr>
                <td class="calendarhead"><a href="<?php echo createArchiveLink($blogid, $last_year . '-' . $last_month) ?>"> <?php echo $prev; ?></a>
                <?php
            }

            $mday = 1;
            $to_day = date("j");
            $this_month = date("n");
            while (checkdate($month, $mday, $year)) {
                $mday_f = date("d", mktime(0, 0, 0, $month, $mday, $year));
                if ($mday == $to_day && $this_month == $month)
                    echo '<td class="today">';
                else
                    echo '<td class="days">';

                if (($linkall == 'yes' && $nolink == 'no') || $days[$mday])
                    echo '<a href="', createArchiveLink($blogid, $year . '-' . $month . '-' . $mday), '">', $mday_f, '</a></td>';
                else
                    echo $mday_f, '</td>';

                $mday++;
                $wday++;
                if (($wday > 7) && (checkdate($month, $mday, $year))) {
                    $wday = 1;
                }
            }

            // footer
                ?>
                <td class="calendarhead"><a href="<?php echo createArchiveLink($blogid, $next_year . '-' . $next_month) ?>"><?php echo $next ?></a></td>
        <?php
        echo '</tr></table>';
        echo "\n<!-- kalendar end -->\n";
    }
}
