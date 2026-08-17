const JobCard = ({ job }) => {
  return (
    <div className="bg-white p-5 rounded-xl shadow hover:shadow-lg transition">
      <img
        src="https://images.unsplash.com/photo-1492724441997-5dc865305da7"
        className="rounded mb-3 h-40 w-full object-cover"
      />
      <h3 className="text-lg font-bold">{job.title}</h3>
      <p className="text-gray-500">{job.company}</p>
      <p className="text-sm mt-2">{job.location}</p>
      <button className="mt-3 bg-blue-600 text-white px-4 py-2 rounded">
        Apply
      </button>
    </div>
  );
};

export default JobCard;