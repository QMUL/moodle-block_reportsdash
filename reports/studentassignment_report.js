M.studentassignment_functions = {

    init:    function() {
        Y.one('#id_coursefilter').on('change', this.ajax_course,this);
    },

    ajax_course: function() {

        var uri = "reports/studentslist.php?courseid="+Y.one('#id_coursefilter').get("value");

        function complete(i,data){
            var students =  Y.JSON.parse(data.responseText);
            Y.one('#id_studentfilter').get('options').remove();
            name = "Select Student";
            id = 0;
            Y.one('#id_studentfilter').append("<option value='"+id+"'>"+name+"</option>").get('value');
            for (var i = 0; i < students.length; i++) {
                student = students[i];
                Y.one('#id_studentfilter').append("<option value='"+student.id+"'>"+student.name+"</option>").get('value');
            }
        };
        // Subscribe to event "io:complete", and pass an array
        // as an argument to the event handler "complete", since
        // "complete" is global.   At this point in the transaction
        // lifecycle, success or failure is not yet known.
        Y.on('io:complete', complete, Y, ['lorem', 'ipsum']);

        var request = Y.io(uri);

    }
}