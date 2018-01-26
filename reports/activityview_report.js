/**
 * Created by n.narayanan on 19/02/14.
 */

M.activityview_functions = {

    init:    function() {
        Y.one('#id_coursefilter').on('change', this.ajax_course,this);
    },

    ajax_course: function() {

        var uri = "reports/courseinfo.php?courseid="+Y.one('#id_coursefilter').get("value");


        function complete(i,data){
            var modules =  Y.JSON.parse(data.responseText);
            Y.one('#id_activityfilter').get('options').remove();
            id = 0;
            name = "All activities";
            Y.one('#id_activityfilter').append("<option value='"+id+"'>"+name+"</option>").get('value');
            for (var i = 0; i < modules.length; i++) {
                mod = modules[i];
                Y.one('#id_activityfilter').append("<option value='"+mod.id+"'>"+mod.name+"</option>").get('value');
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