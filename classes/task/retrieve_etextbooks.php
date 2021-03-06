<?php
namespace block_etextbook\task;
global $CFG;

class retrieve_etextbooks extends \core\task\scheduled_task
{
    public function get_name()
    {
        return get_string('retrieve_etextbooks', 'block_etextbook');
    }

    public function execute()
    {
        global $DB;

        $librarylink = get_config('etextbook', 'Library_link');
        echo "\n library link is \n". $librarylink . "\n";
        $books = simplexml_load_file($librarylink);

        if($books == false){
            echo "\n FILE FROM LIBRARY XML WAS NOT ACCEPTED AS XML \n";
            return;
        }

        else if(!isset($books->book->field_ebook_url)){
            echo "\n DATA DOES NOT CONTAIN A field_ebook_url NODE\n" .
                 "\n likely the XML file is formatted or was recieved incorrectly\n";
            return;
        }

        else if($books->count() < 1){
            echo "\n BOOK COUNT WAS LESS THAN 1 \n";
            return;
        }

        else{
            echo "\n------------------------------\n";
            echo "\n\n number of books return = " . $books->count() . " \n";
            echo "\n DELETING PREVIOUS DATA FROM DB\n";
            $DB->execute("TRUNCATE TABLE {block_etextbook}");
        }

        $tbook = new \stdClass();
        $foundbookstring = "";

        // For loop to get all course numbers with books
        foreach ($books as $book) {
            $tbook->book_url =          (string)$book->field_ebook_url;
            $tbook->img_url =           (string)$book->field_ebook_image;
            $tbook->title =             (string)$book->field_ebook_title;
            $tbook->dept =              (string)$book->field_ebook_subject;
            $tbook->course_title =      (string)$book->field_course_title;
            $tbook->course_number =     (string)$book->field_course_number;
            $tbook->section =           (string)$book->field_ebook_section;
            $tbook->instructor =        (string)$book->Instructor;
            $tbook->term =              (string)$book->Term;
            $termswitcharoo =           explode(" ", $tbook->term);
            $tbook->term =              $termswitcharoo[1] . " " . $termswitcharoo[0];

            if(strlen($tbook->section) > 1){
                $sections = explode(',', ($tbook->section));
                foreach($sections as $section){
                    $tbook->section = $section;
                    if($foundbookstring == ""){
                        $foundbookstring = $this->merge_courses_with_books($tbook);
                    }
                    else{
                        $foundbookstring .= "\t" . $this->merge_courses_with_books($tbook);
                    }
                }
            }
            else{
                if($foundbookstring == "") {
                    $foundbookstring = $this->merge_courses_with_books($tbook);
                }
                else{
                    $foundbookstring .= "\t" . $this->merge_courses_with_books($tbook);
                }
            }
        }
        echo "\n" . $foundbookstring;
    }
    public function merge_courses_with_books($tbook){
        global $DB;
        $tbook->courseid = "";
        $coursenameregexp = $tbook->term . ' ' . $tbook->dept . ' ' . $tbook->course_number . ' ' . str_pad($tbook->section, 3, "0", STR_PAD_LEFT);
        $foundbookstring =  "- " . $tbook->dept . " " . $tbook->course_number;

        $sqlt = "SELECT DISTINCT(c.id)
                     FROM {enrol_ues_semesters} sem
                     INNER JOIN {enrol_ues_sections} sec ON sec.semesterid = sem.id
                     INNER JOIN {enrol_ues_courses} cou ON cou.id = sec.courseid
                     INNER JOIN {course} c ON c.idnumber = sec.idnumber
                     WHERE sec.idnumber IS NOT NULL
                     AND c.idnumber IS NOT NULL
                     AND sec.idnumber <> ''
                     AND c.idnumber <> ''
                     AND CONCAT(sem.year, ' ', sem.name, ' ', cou.department, ' ', cou.cou_number, ' ', sec.sec_number) = :coursename";

        if($records = $DB->get_record_sql($sqlt, array('coursename' => $coursenameregexp))){
            $tbook->courseid = $records->id;
            $DB->insert_record('block_etextbook', $tbook);
        }
        else{
            echo "---- ETEXTBOOK ALERT ---- book found but not matched for " .$coursenameregexp . " ---- \n";
        }
        return $foundbookstring;
    }

}

