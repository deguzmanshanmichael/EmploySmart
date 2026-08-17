const ApplicationCard = ({ application }) => {
  return (
    <div className="bg-white p-5 rounded-xl shadow">
      <h3 className="font-bold">{application.job_title}</h3>
      <p className="text-gray-500">{application.company}</p>
      <span className="text-sm text-blue-600">
        Status: {application.status}
      </span>
    </div>
  );
};

export default ApplicationCard;